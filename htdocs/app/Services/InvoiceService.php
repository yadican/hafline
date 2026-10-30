<?php

namespace App\Services;

use App\Cache\DashboardCache;
use App\Cache\FormOptionsCache;
use App\Enums\CariEntryKind;
use App\Enums\CashDirection;
use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceRecipient;
use App\Enums\PaymentMethod;
use App\Exceptions\OperationRejectedException;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\StockItem;
use App\Models\Vehicle;
use App\Repositories\BankRepository;
use App\Repositories\CashCategoryRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use App\Repositories\StockItemRepository;
use App\Services\Api\SecureImageIngest;
use App\Services\Ledger\InvoiceLedger;
use App\Services\Ledger\LedgerLine;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class InvoiceService extends Service
{
    public const MAX_ATTACHMENTS = 4;

    private const ATTACHMENT_FOLDER = 'invoice-attachments';

    public function __construct(
        private InvoiceRepository $invoices,
        private PartyRepository $parties,
        private SiteRepository $sites,
        private CashCategoryRepository $categories,
        private StockItemRepository $stockItems,
        private StockService $stock,
        private InvoiceLedger $ledger,
        private InvoicePaymentService $payments,
        private CariEntryService $cariEntries,
        private BankRepository $banks,
        private SecureImageIngest $images,
        private VehicleOwnershipService $ownerships,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->invoices->search($filters, $sort, $perPage);
    }

    /**
     * @return array{
     *     directions: array<string, string>,
     *     parties: array<string, string>,
     *     drafts: array<string, string>,
     *     due_presets: list<array{key: string, label: string, filters: array<string, string>}>
     * }
     */
    public function searchOptions(): array
    {
        $today = now()->copy()->startOfDay();

        return [
            'directions' => InvoiceDirection::options(),
            'parties' => $this->parties->options(),
            'drafts' => ['1' => 'Taslaklar', '0' => 'Onaylılar'],
            'due_presets' => [
                [
                    'key' => 'overdue',
                    'label' => 'Vadesi geçen',
                    'filters' => ['overdue' => '1'],
                ],
                [
                    'key' => 'week',
                    'label' => 'Bu hafta',
                    'filters' => [
                        'due_from' => $today->copy()->startOfWeek()->toDateString(),
                        'due_to' => $today->copy()->endOfWeek()->toDateString(),
                    ],
                ],
                [
                    'key' => 'month',
                    'label' => 'Bu ay',
                    'filters' => [
                        'due_from' => $today->copy()->startOfMonth()->toDateString(),
                        'due_to' => $today->copy()->endOfMonth()->toDateString(),
                    ],
                ],
            ],
        ];
    }

    /**
     * Line categories are offered per direction: expense ones for a purchase / purchase return,
     * income ones for a sale / sale return. Stock items are optional on a line; a tracked item on a
     * live purchase or sale return enters stock, on a live sale leaves at that day's average, on a
     * live purchase return leaves at the return line's purchase cost (matrah). Open purchases and
     * sales are offered so a return can reduce that invoice's remaining.
     *
     * @return array{directions: array<string, string>, parties: array<string, string>, sites: array<string, string>, categories: array<string, array<string, string>>, stock_items: array<string, string>, stock_item_meta: array<string, array{name: string, cash_category_id: ?string, tracks_stock: bool}>, returnable_invoices: array{purchase: array<string, array<string, string>>, sale: array<string, array<string, string>>}}
     */
    public function formOptions(?Invoice $invoice = null): array
    {
        $categoryIds = $invoice?->lines->pluck('cash_category_id')->filter()->values()->all() ?? [];
        $stockItemIds = $invoice?->lines->pluck('stock_item_id')->filter()->values()->all() ?? [];
        $expense = $this->categories->options(CashDirection::Expense, $categoryIds);
        $income = $this->categories->options(CashDirection::Income, $categoryIds);
        $includeReturnable = $invoice?->returns_invoice_id;

        return [
            'directions' => InvoiceDirection::options(),
            'parties' => $this->parties->withoutSystem($this->parties->options(include: $invoice?->party_id)),
            'sites' => $this->sites->options(activeOnly: true, include: $invoice?->site_id),
            'categories' => [
                InvoiceDirection::Purchase->value => $expense,
                InvoiceDirection::PurchaseReturn->value => $expense,
                InvoiceDirection::Sale->value => $income,
                InvoiceDirection::SaleReturn->value => $income,
            ],
            'recipients' => InvoiceRecipient::options(),
            'stock_items' => $this->stockItems->options($stockItemIds),
            'stock_item_meta' => $this->stockItemMeta($stockItemIds),
            'returnable_invoices' => [
                'purchase' => $this->invoices->returnablePurchases($includeReturnable),
                'sale' => $this->invoices->returnableSales($includeReturnable),
            ],
        ];
    }

    /**
     * The invoice's lines as the form edits them.
     *
     * @return list<array{description: string, quantity: string, unit_price: string, vat_rate: string, cash_category_id: string, stock_item_id: string}>
     */
    public function formLines(Invoice $invoice): array
    {
        $plain = fn (mixed $decimal): string => (string) (float) $decimal;

        return $invoice->lines
            ->map(fn (InvoiceLine $line): array => [
                'description' => $line->description,
                'quantity' => $plain($line->quantity),
                'unit_price' => $plain($line->unit_price),
                'vat_rate' => $plain($line->vat_rate),
                'cash_category_id' => (string) ($line->cash_category_id ?? ''),
                'stock_item_id' => (string) ($line->stock_item_id ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId): Invoice
    {
        return $this->transaction(function () use ($data, $userId): Invoice {
            $this->assertStockExit($data);
            [$attributes, $lines] = $this->attributes($data);
            $this->assertPlateOpen($attributes['vehicle_id'] ?? null, Carbon::parse($attributes['issued_on']));
            $draft = (bool) ($data['is_draft'] ?? false);

            if (! $draft) {
                $this->assertReturnFitsTarget($attributes);
            }

            /** @var Invoice $invoice */
            $invoice = $this->invoices->create([...$attributes, 'is_draft' => $draft, 'created_by' => $userId]);
            $this->invoices->replaceLines($invoice, $lines);

            if (! $draft) {
                $this->ledger->sync($invoice);
                $this->payAtEntry($invoice, $data, $userId);
                DashboardCache::forgetInvoiceReminders();
            }

            $this->addAttachments($invoice, $data['attachments'] ?? [], $userId);
            $this->flashUnderCostWarning($lines, $attributes['direction'], $data['issued_on']);

            return $invoice;
        });
    }

    /**
     * What the lines add up to, rounded line by line as the invoice stores them.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function total(array $lines): float
    {
        return round(array_sum(array_map(fn (array $line): float => (float) $this->line($line)['total'], $lines)), 2);
    }

    /**
     * Editing a draft keeps it a draft until approve(); a live invoice stays live. Drafts write no
     * cari lines and do not touch the reminder cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data, ?string $userId = null): Invoice
    {
        return $this->transaction(function () use ($invoice, $data, $userId): Invoice {
            $payload = [...$data, 'is_draft' => $invoice->is_draft];
            $this->assertStockExit($payload, $invoice->isDraft() ? null : $invoice);
            $this->assertPurchaseStock($invoice, $payload);
            [$attributes, $lines] = $this->attributes($payload);
            $this->assertPlatePeriod($invoice);
            $this->assertPlateOpen($attributes['vehicle_id'] ?? null, Carbon::parse($attributes['issued_on']));
            $this->payments->assertInvoiceKeepsItsPayments($invoice, $attributes['party_id'], $attributes['direction'], $attributes['total']);
            $this->payments->assertInvoiceKeepsItsReturns($invoice, $attributes['total']);

            if (! $invoice->isDraft()) {
                $this->assertReturnFitsTarget($attributes, $invoice);
            }

            /** @var Invoice $updated */
            $updated = $this->invoices->update($invoice, $attributes);
            $this->invoices->replaceLines($updated, $lines);

            if (! $updated->isDraft()) {
                $this->ledger->sync($updated);
                DashboardCache::forgetInvoiceReminders();
            }

            $this->addAttachments($updated, $data['attachments'] ?? [], $userId);
            $this->flashUnderCostWarning($lines, $attributes['direction'], $payload['issued_on']);

            return $updated;
        });
    }

    /**
     * Posts the draft to cari like a normal create; afterwards it behaves as a live invoice.
     */
    public function approve(Invoice $invoice): Invoice
    {
        if (! $invoice->isDraft()) {
            throw new OperationRejectedException('Bu fatura zaten onaylı.');
        }

        return $this->transaction(function () use ($invoice): Invoice {
            $invoice->loadMissing('lines');
            $this->assertPlatePeriod($invoice);
            $this->assertStockExit([
                'direction' => $invoice->direction->value,
                'issued_on' => $invoice->issued_on->toDateString(),
                'is_draft' => false,
                'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                    'stock_item_id' => $line->stock_item_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                ])->all(),
            ]);
            $this->assertReturnFitsTarget([
                'direction' => $invoice->direction,
                'party_id' => $invoice->party_id,
                'returns_invoice_id' => $invoice->returns_invoice_id,
                'total' => $invoice->total,
            ]);

            /** @var Invoice $approved */
            $approved = $this->invoices->update($invoice, ['is_draft' => false]);
            $this->freezeSaleLineCosts($approved);
            $this->freezeSaleReturnLineCosts($approved);
            $this->ledger->sync($approved);
            DashboardCache::forgetInvoiceReminders();
            $this->flashUnderCostWarning(
                $approved->lines->map(fn (InvoiceLine $line): array => [
                    'stock_item_id' => $line->stock_item_id,
                    'unit_price' => $line->unit_price,
                    'unit_cost' => $line->unit_cost,
                ])->all(),
                $approved->direction,
                $approved->issued_on->toDateString(),
            );

            return $approved;
        });
    }

    /**
     * Closes the dashboard due reminder by hand; the invoice stays unpaid until matched.
     */
    public function dismissReminder(Invoice $invoice): void
    {
        if ($invoice->reminder_dismissed_at !== null) {
            return;
        }

        $this->invoices->update($invoice, ['reminder_dismissed_at' => now()]);
        DashboardCache::forgetInvoiceReminders();
    }

    /**
     * Pays a purchase (Satıcı Cari, debit) or collects a sale (Alıcı Cari, credit) as a cari entry on
     * the invoice's party and matches it to the invoice, in one transaction. A cheque or note goes
     * into the portfolio through the cari entry. The amount cannot exceed the invoice's remaining.
     *
     * @param  array{amount: string|float, occurred_on: string, payment_method: string, bank_id?: ?string, cheque_no?: ?string, due_on?: ?string, instrument_bank?: ?string, instrument_drawer?: ?string, description?: ?string}  $payment
     */
    public function pay(Invoice $invoice, array $payment, ?string $userId): InvoicePayment
    {
        $this->assertPayable($invoice);

        return $this->transaction(function () use ($invoice, $payment, $userId): InvoicePayment {
            [$kind, $direction] = match ($invoice->direction) {
                InvoiceDirection::Purchase => [CariEntryKind::Supplier, 'debit'],
                InvoiceDirection::Sale => [CariEntryKind::Customer, 'credit'],
                InvoiceDirection::PurchaseReturn => throw new OperationRejectedException(
                    'Alış iadesi faturası buradan tahsil edilmez; açık alış faturasına bağlayın veya cari hareketi kullanın.',
                ),
                InvoiceDirection::SaleReturn => throw new OperationRejectedException(
                    'Satış iadesi faturası buradan ödenmez; açık satış faturasına bağlayın veya cari hareketi kullanın.',
                ),
            };

            $entry = $this->cariEntries->create([
                'kind' => $kind->value,
                'direction' => $direction,
                'party_id' => $invoice->party_id,
                'amount' => LedgerLine::money($payment['amount']),
                'occurred_on' => $payment['occurred_on'],
                'payment_method' => $payment['payment_method'],
                'bank_id' => $payment['bank_id'] ?? null,
                'document_no' => $invoice->invoice_no,
                'cheque_no' => $payment['cheque_no'] ?? null,
                'due_on' => $payment['due_on'] ?? null,
                'instrument_bank' => $payment['instrument_bank'] ?? null,
                'instrument_drawer' => $payment['instrument_drawer'] ?? null,
                'description' => ($payment['description'] ?? null) ?: sprintf('%s faturası %s', $invoice->direction->label(), $invoice->invoice_no),
            ], $userId);

            return $this->payments->match($invoice, $entry, $payment['amount'], $userId);
        });
    }

    /**
     * Matches a payment already recorded for the invoice's party; without an amount the largest
     * possible part is matched.
     *
     * @param  array{payment_type: string, payment_id: string, amount?: string|float|null}  $data
     */
    public function matchPayment(Invoice $invoice, array $data, ?string $userId): InvoicePayment
    {
        $this->assertPayable($invoice);

        $class = Relation::getMorphedModel($data['payment_type']);
        $payment = $class ? $class::query()->find($data['payment_id']) : null;

        if (! $payment instanceof Model) {
            throw new OperationRejectedException('Ödeme kaydı bulunamadı.');
        }

        return $this->payments->match($invoice, $payment, $data['amount'] ?? null, $userId);
    }

    /**
     * @return Collection<int, InvoicePayment>
     */
    public function distributePayments(Invoice $invoice, ?string $userId): Collection
    {
        $this->assertPayable($invoice);

        return $this->payments->distribute($invoice, $userId);
    }

    public function unmatchPayment(InvoicePayment $match): void
    {
        $this->payments->unmatch($match);
    }

    /**
     * The party's payments still waiting to be matched; only listed while the invoice has a remaining.
     *
     * @return list<array{payment: Model, occurred_on: CarbonInterface, payment_method: ?PaymentMethod, description: ?string, amount: float, unmatched: float}>
     */
    public function unmatchedPayments(Invoice $invoice): array
    {
        if (! $invoice->direction->acceptsPayment() || $invoice->remainingAmount() <= 0) {
            return [];
        }

        return $this->payments->unmatchedPayments($invoice);
    }

    /**
     * @return array{methods: array<string, string>, accounts: array<string, string>}
     */
    public function paymentOptions(): array
    {
        return [
            'methods' => PaymentMethod::options(),
            'accounts' => $this->banks->accountOptions(),
        ];
    }

    /**
     * The new invoice form's payment status choices, labelled per direction, with the payment options.
     *
     * @return array{statuses: array<string, array<string, string>>, methods: array<string, string>, accounts: array<string, string>}
     */
    public function entryPaymentOptions(): array
    {
        return ['statuses' => InvoicePaymentStatus::entryOptions(), ...$this->paymentOptions()];
    }

    public function ledgerEntry(Invoice $invoice): ?LedgerEntry
    {
        return $this->ledger->entry($invoice);
    }

    public function delete(Invoice $invoice): void
    {
        $this->transaction(function () use ($invoice): void {
            $this->assertPlatePeriod($invoice);
            $this->payments->assertInvoiceHasNoPayments($invoice);
            $this->payments->assertInvoiceHasNoReturns($invoice);
            $this->assertPurchaseStock($invoice, action: 'delete');

            if (! $invoice->isDraft()) {
                $this->ledger->void($invoice);
                DashboardCache::forgetInvoiceReminders();
            }

            $this->invoices->delete($invoice);
        });
    }

    /**
     * Pictures are downscaled and re-encoded; a PDF is kept as uploaded.
     *
     * @param  list<UploadedFile>  $files
     */
    public function addAttachments(Invoice $invoice, array $files, ?string $userId): void
    {
        $this->transaction(function () use ($invoice, $files, $userId): void {
            foreach (array_values($files) as $index => $file) {
                $name = sprintf('%s-%s-%d', $invoice->getKey(), now()->format('YmdHisv'), $index);

                $invoice->attachments()->create([
                    'path' => $this->images->isPdf($file)
                        ? $this->images->storePdf($file, self::ATTACHMENT_FOLDER, $name)
                        : $this->images->storeDocument($file, self::ATTACHMENT_FOLDER, $name),
                    'created_by' => $userId,
                ]);
            }
        });
    }

    public function deleteAttachment(InvoiceAttachment $attachment): void
    {
        $attachment->delete();
    }

    /**
     * @return array{path: string, mime: string}
     */
    public function attachmentFile(InvoiceAttachment $attachment): array
    {
        return ['path' => $this->images->absolutePath($attachment->path), 'mime' => $this->images->mimeForPath($attachment->path)];
    }

    /**
     * A new invoice entered as paid is paid in full on its own date, as partly paid by the amount
     * entered (K3); unpaid writes nothing.
     *
     * @param  array<string, mixed>  $data
     */
    private function payAtEntry(Invoice $invoice, array $data, ?string $userId): void
    {
        $amount = match (InvoicePaymentStatus::tryFrom((string) ($data['payment_status'] ?? ''))) {
            InvoicePaymentStatus::Paid => $invoice->total,
            InvoicePaymentStatus::Partial => $data['payment']['amount'],
            default => null,
        };

        if ($amount === null) {
            return;
        }

        $this->pay($invoice, [...$data['payment'], 'amount' => $amount, 'occurred_on' => $data['issued_on']], $userId);
    }

    /**
     * Each line's base is quantity × unit price, its VAT is the base at the line's rate; the header
     * is the sum of the lines. A plate sale takes the owner that day as party and stores vehicle_id
     * (K8); cari sales keep party_id from the form. The party gets the supplier/customer role except
     * Geçici Cari on a plate sale. Live sale lines of a tracked item freeze that day's average unit cost.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function attributes(array $data): array
    {
        $direction = InvoiceDirection::from($data['direction']);
        $draft = (bool) ($data['is_draft'] ?? false);
        $returnsInvoiceId = $direction->isReturn() ? ($data['returns_invoice_id'] ?? null) : null;
        $lines = array_map(fn (array $line): array => $this->line($line), array_values($data['lines']));
        $lines = $this->withSaleCosts($lines, $direction, $draft, $data['issued_on']);
        $lines = $this->withSaleReturnCosts($lines, $direction, $draft, $returnsInvoiceId);

        [$partyId, $vehicleId] = $this->resolveRecipient($data, $direction);
        $this->assignRole($partyId, $direction, $vehicleId !== null);

        $sum = fn (string $key): string => LedgerLine::money(round(array_sum(array_map(fn (array $line): float => (float) $line[$key], $lines)), 2));

        return [[
            'direction' => $direction,
            'returns_invoice_id' => $returnsInvoiceId,
            'party_id' => $partyId,
            'vehicle_id' => $vehicleId,
            'invoice_no' => $data['invoice_no'],
            'issued_on' => $data['issued_on'],
            'due_on' => $data['due_on'] ?? null,
            'subtotal' => $sum('subtotal'),
            'vat_amount' => $sum('vat_amount'),
            'total' => $sum('total'),
            'site_id' => $data['site_id'] ?? null,
            'description' => $data['description'] ?? null,
        ], $lines];
    }

    /**
     * Sale + Plaka: party is the owner on issued_on; other directions always use the form party.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: ?string}
     */
    private function resolveRecipient(array $data, InvoiceDirection $direction): array
    {
        $recipient = InvoiceRecipient::tryFrom((string) ($data['recipient'] ?? InvoiceRecipient::Party->value))
            ?? InvoiceRecipient::Party;

        if ($direction !== InvoiceDirection::Sale || $recipient !== InvoiceRecipient::Plate) {
            return [(string) $data['party_id'], null];
        }

        $vehicle = Vehicle::findByPlate((string) ($data['plate'] ?? ''))
            ?? throw new OperationRejectedException('Plaka bulunamadı.');
        $issuedOn = Carbon::parse($data['issued_on'])->startOfDay();
        $party = $vehicle->ownershipOn($issuedOn)?->party_id;

        if (! $party) {
            throw new OperationRejectedException(sprintf(
                '%s aracının %s tarihinde kayıtlı sahibi yok.',
                $vehicle->plate,
                $issuedOn->format('d.m.Y'),
            ));
        }

        return [$party, $vehicle->id];
    }

    private function assertPlatePeriod(Invoice $invoice): void
    {
        if ($invoice->vehicle_id === null) {
            return;
        }

        $invoice->loadMissing('vehicle');
        $this->ownerships->assertOpen($invoice->vehicle, $invoice->issued_on, $invoice->party_id);
    }

    private function assertPlateOpen(?string $vehicleId, CarbonInterface $date): void
    {
        if ($vehicleId === null) {
            return;
        }

        $vehicle = Vehicle::query()->find($vehicleId)
            ?? throw new OperationRejectedException('Plaka bulunamadı.');

        $this->ownerships->assertOpen($vehicle, $date);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function line(array $line): array
    {
        $vatRate = (float) ($line['vat_rate'] ?? 0);
        $subtotal = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
        $vat = round($subtotal * $vatRate / 100, 2);

        return [
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'vat_rate' => $vatRate,
            'subtotal' => LedgerLine::money($subtotal),
            'vat_amount' => LedgerLine::money($vat),
            'total' => LedgerLine::money($subtotal + $vat),
            'cash_category_id' => $line['cash_category_id'] ?? null,
            'stock_item_id' => $line['stock_item_id'] ?? null,
            'unit_cost' => null,
        ];
    }

    /**
     * Live tracked sales and purchase returns cannot leave book stock negative on that day or
     * afterwards (K10). Drafts are checked on approve. When editing a live exit, its current lines
     * are excluded first.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertStockExit(array $data, ?Invoice $excluding = null): void
    {
        $direction = $data['direction'] instanceof InvoiceDirection
            ? $data['direction']
            : InvoiceDirection::tryFrom((string) ($data['direction'] ?? ''));

        if (! in_array($direction, [InvoiceDirection::Sale, InvoiceDirection::PurchaseReturn], true)) {
            return;
        }

        if ((bool) ($data['is_draft'] ?? false)) {
            return;
        }

        /** @var array<string, float> $quantities */
        $quantities = [];

        foreach ($data['lines'] ?? [] as $line) {
            $itemId = $line['stock_item_id'] ?? null;

            if ($itemId === null || $itemId === '') {
                continue;
            }

            $quantities[(string) $itemId] = ($quantities[(string) $itemId] ?? 0.0) + (float) $line['quantity'];
        }

        foreach ($quantities as $itemId => $quantity) {
            $item = StockItem::query()->find($itemId);

            if ($item === null || ! $item->tracks_stock) {
                continue;
            }

            if ($direction === InvoiceDirection::PurchaseReturn) {
                $this->stock->assertPurchaseReturnKeepsStockNonNegative(
                    $item,
                    (string) $data['issued_on'],
                    $quantity,
                    $excluding?->id,
                );

                continue;
            }

            $this->stock->assertSaleKeepsStockNonNegative(
                $item,
                (string) $data['issued_on'],
                $quantity,
                $excluding?->id,
            );
        }
    }

    /**
     * A live purchase that fed later sales, usages, purchase returns or downward counts cannot
     * shrink below that used quantity, and cannot be deleted while those exits still need it.
     *
     * @param  array<string, mixed>|null  $data
     * @param  'update'|'delete'  $action
     */
    private function assertPurchaseStock(Invoice $invoice, ?array $data = null, string $action = 'update'): void
    {
        if ($invoice->isDraft() || $invoice->direction !== InvoiceDirection::Purchase) {
            return;
        }

        $invoice->loadMissing('lines');

        /** @var array<string, float> $proposed */
        $proposed = [];

        if ($action === 'update' && $data !== null) {
            $direction = $data['direction'] instanceof InvoiceDirection
                ? $data['direction']
                : InvoiceDirection::tryFrom((string) ($data['direction'] ?? ''));

            if ($direction === InvoiceDirection::Purchase && ! (bool) ($data['is_draft'] ?? false)) {
                foreach ($data['lines'] ?? [] as $line) {
                    $itemId = $line['stock_item_id'] ?? null;

                    if ($itemId === null || $itemId === '') {
                        continue;
                    }

                    $proposed[(string) $itemId] = ($proposed[(string) $itemId] ?? 0.0) + (float) $line['quantity'];
                }
            }

            $issuedOn = (string) $data['issued_on'];
        } else {
            $issuedOn = $invoice->issued_on->toDateString();
        }

        $itemIds = collect($invoice->lines->pluck('stock_item_id')->filter()->map(fn ($id): string => (string) $id))
            ->merge(array_keys($proposed))
            ->unique()
            ->values();

        foreach ($itemIds as $itemId) {
            $item = StockItem::query()->find($itemId);

            if ($item === null || ! $item->tracks_stock) {
                continue;
            }

            $this->stock->assertPurchaseKeepsUsedStock(
                $item,
                $issuedOn,
                $proposed[$itemId] ?? 0.0,
                $invoice->id,
                $action,
            );
        }
    }

    /**
     * A linked return may not exceed the open target invoice's remaining (payments + other live
     * returns). Same party, live purchase or sale matching the return type, not a draft.
     *
     * @param  array{direction: InvoiceDirection, party_id: string, returns_invoice_id?: ?string, total: string|float}  $attributes
     */
    private function assertReturnFitsTarget(array $attributes, ?Invoice $excluding = null): void
    {
        $direction = $attributes['direction'];
        $expectedTarget = $direction->returnTarget();

        if ($expectedTarget === null) {
            return;
        }

        $targetId = $attributes['returns_invoice_id'] ?? null;

        if ($targetId === null || $targetId === '') {
            return;
        }

        $target = Invoice::query()->lockForUpdate()->find($targetId);
        $targetLabel = mb_strtolower($expectedTarget->label(), 'UTF-8');

        if ($target === null || $target->direction !== $expectedTarget || $target->isDraft()) {
            throw new OperationRejectedException(sprintf('İade, onaylı bir %s faturasına bağlanmalıdır.', $targetLabel));
        }

        if ($target->party_id !== $attributes['party_id']) {
            throw new OperationRejectedException(sprintf(
                'İade, aynı carinin %s faturasına bağlanmalıdır.',
                $targetLabel,
            ));
        }

        $remaining = $target->remainingAmount();

        if ($excluding !== null && $excluding->returns_invoice_id === $target->id && ! $excluding->isDraft()) {
            $remaining = round($remaining + (float) $excluding->total, 2);
        }

        if (round((float) $attributes['total'], 2) > $remaining) {
            throw new OperationRejectedException(sprintf(
                'İade tutarı, bağlı %s faturasının kalanını (%s) aşamaz.',
                $targetLabel,
                number_format($remaining, 2, ',', '.').' TL',
            ));
        }
    }

    /**
     * Under-cost sale is allowed (K8); flash an info toast so the user sees it without blocking.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function flashUnderCostWarning(array $lines, InvoiceDirection $direction, string $issuedOn): void
    {
        if ($direction !== InvoiceDirection::Sale) {
            return;
        }

        $parts = [];

        foreach ($lines as $line) {
            $itemId = $line['stock_item_id'] ?? null;

            if ($itemId === null || $itemId === '') {
                continue;
            }

            $item = StockItem::query()->find($itemId);

            if ($item === null || ! $item->tracks_stock) {
                continue;
            }

            $cost = array_key_exists('unit_cost', $line) && $line['unit_cost'] !== null
                ? (float) $line['unit_cost']
                : $this->stock->stock($item, Carbon::parse($issuedOn))['average_cost'];
            $price = (float) $line['unit_price'];

            if ($price + 0.00005 >= $cost) {
                continue;
            }

            $parts[] = sprintf('%s (maliyet %s, satış %s)', $item->name, $this->money($cost), $this->money($price));
        }

        if ($parts === []) {
            return;
        }

        session()->flash('info', 'Maliyetin altında satış: '.implode('; ', $parts).'.');
    }

    /**
     * For a live sale, each tracked stock line stores that day's weighted average as unit_cost.
     * Drafts and purchases leave it null.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function withSaleCosts(array $lines, InvoiceDirection $direction, bool $draft, string $issuedOn): array
    {
        if ($direction !== InvoiceDirection::Sale || $draft) {
            return $lines;
        }

        /** @var array<string, ?float> $costs */
        $costs = [];

        return array_map(function (array $line) use ($issuedOn, &$costs): array {
            $itemId = $line['stock_item_id'] ?? null;
            if ($itemId === null || $itemId === '') {
                return $line;
            }

            if (! array_key_exists($itemId, $costs)) {
                $item = StockItem::query()->find($itemId);
                $costs[$itemId] = ($item !== null && $item->tracks_stock)
                    ? $this->stock->stock($item, Carbon::parse($issuedOn))['average_cost']
                    : null;
            }

            $line['unit_cost'] = $costs[$itemId];

            return $line;
        }, $lines);
    }

    /**
     * Live sale returns re-enter stock at the original sale's frozen unit_cost (not today's
     * average). Prefer the linked sale's lines; otherwise the most recent live sale of the item.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function withSaleReturnCosts(array $lines, InvoiceDirection $direction, bool $draft, ?string $returnsInvoiceId): array
    {
        if ($direction !== InvoiceDirection::SaleReturn || $draft) {
            return $lines;
        }

        /** @var array<string, float> $fromLinked */
        $fromLinked = $this->saleUnitCostsFromInvoice($returnsInvoiceId);

        /** @var array<string, ?float> $costs */
        $costs = [];

        return array_map(function (array $line) use ($fromLinked, &$costs): array {
            $itemId = $line['stock_item_id'] ?? null;
            if ($itemId === null || $itemId === '') {
                return $line;
            }

            $item = StockItem::query()->find($itemId);

            if ($item === null || ! $item->tracks_stock) {
                return $line;
            }

            if (! array_key_exists($itemId, $costs)) {
                $costs[$itemId] = $fromLinked[$itemId] ?? $this->latestSaleUnitCost($item);
            }

            if ($costs[$itemId] === null) {
                throw new OperationRejectedException(sprintf(
                    '%s için satış maliyeti bulunamadı; açık satış faturasına bağlayın.',
                    $item->name,
                ));
            }

            $line['unit_cost'] = $costs[$itemId];

            return $line;
        }, $lines);
    }

    /**
     * Weighted unit_cost per stock item from a live sale invoice's lines.
     *
     * @return array<string, float>
     */
    private function saleUnitCostsFromInvoice(?string $saleId): array
    {
        if ($saleId === null || $saleId === '') {
            return [];
        }

        $sale = Invoice::query()
            ->with('lines')
            ->whereKey($saleId)
            ->where('direction', InvoiceDirection::Sale)
            ->where('is_draft', false)
            ->first();

        /** @var array<string, array{qty: float, value: float}> $buckets */
        $buckets = [];

        foreach ($sale?->lines ?? [] as $saleLine) {
            if ($saleLine->stock_item_id === null || $saleLine->unit_cost === null) {
                continue;
            }

            $itemId = (string) $saleLine->stock_item_id;
            $qty = (float) $saleLine->quantity;
            $buckets[$itemId] ??= ['qty' => 0.0, 'value' => 0.0];
            $buckets[$itemId]['qty'] += $qty;
            $buckets[$itemId]['value'] += $qty * (float) $saleLine->unit_cost;
        }

        return collect($buckets)
            ->filter(fn (array $row): bool => $row['qty'] > 0)
            ->map(fn (array $row): float => round($row['value'] / $row['qty'], 4))
            ->all();
    }

    /**
     * Most recent live sale line unit_cost for this stock item (sale-day cost when unlinked).
     */
    private function latestSaleUnitCost(StockItem $item): ?float
    {
        $line = InvoiceLine::query()
            ->select('invoice_lines.*')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoice_lines.stock_item_id', $item->id)
            ->whereNotNull('invoice_lines.unit_cost')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.direction', InvoiceDirection::Sale)
            ->where('invoices.is_draft', false)
            ->orderByDesc('invoices.issued_on')
            ->orderByDesc('invoices.id')
            ->first();

        return $line?->unit_cost !== null ? (float) $line->unit_cost : null;
    }

    /**
     * When a draft sale is approved, freeze costs on its tracked lines (create/update already did
     * this for invoices saved as live).
     */
    private function freezeSaleLineCosts(Invoice $invoice): void
    {
        if ($invoice->direction !== InvoiceDirection::Sale) {
            return;
        }

        $invoice->loadMissing('lines');
        $issuedOn = $invoice->issued_on->toDateString();

        foreach ($invoice->lines as $line) {
            if ($line->stock_item_id === null) {
                continue;
            }

            $item = StockItem::query()->find($line->stock_item_id);
            if ($item === null || ! $item->tracks_stock) {
                continue;
            }

            $line->update([
                'unit_cost' => $this->stock->stock($item, Carbon::parse($issuedOn))['average_cost'],
            ]);
        }

        $invoice->unsetRelation('lines');
        $invoice->load('lines');
    }

    /**
     * When a draft sale return is approved, freeze each tracked line's unit_cost from the linked
     * or most recent sale (create/update already did this for live returns).
     */
    private function freezeSaleReturnLineCosts(Invoice $invoice): void
    {
        if ($invoice->direction !== InvoiceDirection::SaleReturn) {
            return;
        }

        $invoice->loadMissing('lines');
        $lines = $this->withSaleReturnCosts(
            $invoice->lines->map(fn (InvoiceLine $line): array => [
                'stock_item_id' => $line->stock_item_id,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'unit_cost' => null,
            ])->all(),
            InvoiceDirection::SaleReturn,
            false,
            $invoice->returns_invoice_id,
        );

        foreach ($invoice->lines as $index => $line) {
            $cost = $lines[$index]['unit_cost'] ?? null;

            if ($cost === null) {
                continue;
            }

            $line->update(['unit_cost' => $cost]);
        }

        $invoice->unsetRelation('lines');
        $invoice->load('lines');
    }

    private function money(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 4, ',', '.'), '0'), ',').' TL';
    }

    /**
     * Name, default category, sale price and whether the item enters stock — used by the line form when picking.
     *
     * @param  list<string>  $include
     * @return array<string, array{name: string, cash_category_id: ?string, tracks_stock: bool, sales_price: ?string}>
     */
    private function stockItemMeta(array $include = []): array
    {
        return StockItem::query()
            ->where(fn ($query) => $query->where('active', true)->orWhereIn('id', $include))
            ->orderBy('name')
            ->get(['id', 'name', 'cash_category_id', 'tracks_stock', 'sales_price'])
            ->mapWithKeys(fn (StockItem $item): array => [
                $item->id => [
                    'name' => $item->name,
                    'cash_category_id' => $item->cash_category_id,
                    'tracks_stock' => $item->tracks_stock,
                    'sales_price' => $item->sales_price !== null && (float) $item->sales_price > 0
                        ? rtrim(rtrim(number_format((float) $item->sales_price, 4, '.', ''), '0'), '.')
                        : null,
                ],
            ])
            ->all();
    }

    private function assertPayable(Invoice $invoice): void
    {
        if ($invoice->isDraft()) {
            throw new OperationRejectedException('Taslak fatura ödenemez; önce onaylayın.');
        }

        if (! $invoice->direction->acceptsPayment()) {
            throw new OperationRejectedException(
                'Alış iadesi faturası buradan tahsil edilmez; açık alış faturasına bağlayın veya cari hareketi kullanın.',
            );
        }
    }

    /**
     * Plate sales may debit Geçici Cari (system) when the plate sits there; a plain cari sale may not.
     */
    private function assignRole(string $partyId, InvoiceDirection $direction, bool $plateSale = false): void
    {
        $party = Party::query()->findOrFail($partyId);

        if ($party->isSystem() && ! ($plateSale && $party->isTemporary())) {
            throw new OperationRejectedException(sprintf('%s carisine fatura kesilemez.', $party->name));
        }

        if ($party->isTemporary()) {
            return;
        }

        $party->assignRole($direction->partyRole());

        FormOptionsCache::flush(FormOptionsCache::PARTIES);
    }
}

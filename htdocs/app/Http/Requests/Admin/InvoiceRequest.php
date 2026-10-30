<?php

namespace App\Http\Requests\Admin;

use App\Enums\CashDirection;
use App\Enums\InvoiceDirection;
use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceRecipient;
use App\Http\Requests\Concerns\ValidatesInvoiceAttachments;
use App\Http\Requests\Concerns\ValidatesInvoicePayment;
use App\Models\CashCategory;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Site;
use App\Models\StockItem;
use App\Models\Vehicle;
use App\Services\InvoiceService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceRequest extends FormRequest
{
    use ValidatesInvoiceAttachments;
    use ValidatesInvoicePayment;

    /**
     * Paying at entry writes a cari entry, so it also needs that permission. Drafts never pay at entry.
     */
    public function authorize(): bool
    {
        return ! $this->paysAtEntry() || (bool) $this->user()?->can('create-cari-entry');
    }

    /**
     * A new invoice paid at entry is paid on its own date. "Ödendi" pays the whole total, so it takes
     * no amount and has no due date of its own; "Kısmen ödendi" keeps both, the due date being for the rest.
     * A draft cannot be paid at entry.
     */
    protected function prepareForValidation(): void
    {
        $this->prepareInvoice();

        if (! $this->isCreating()) {
            return;
        }

        $isReturn = InvoiceDirection::tryFrom((string) $this->input('direction'))?->isReturn() ?? false;
        $plateSale = $this->isPlateSale();

        $this->merge([
            'is_draft' => $this->boolean('is_draft'),
            'payment_status' => $this->isDraft() || $isReturn || $plateSale
                ? InvoicePaymentStatus::Unpaid->value
                : ($this->input('payment_status') ?: InvoicePaymentStatus::Unpaid->value),
            'payment' => [
                ...$this->normalizeInvoicePayment(is_array($this->input('payment')) ? $this->input('payment') : []),
                'occurred_on' => $this->input('issued_on'),
            ],
        ]);

        if ($this->entryPaymentStatus() === InvoicePaymentStatus::Paid) {
            $this->merge(['due_on' => null, 'payment' => [...$this->input('payment'), 'amount' => null]]);
        }
    }

    /**
     * Fully blank lines (a row added on the form and left empty) are dropped. Plate sales resolve
     * the owner into party_id so invoice_no uniqueness stays per cari.
     */
    private function prepareInvoice(): void
    {
        $lines = collect(is_array($this->input('lines')) ? $this->input('lines') : [])
            ->filter(fn (mixed $line): bool => is_array($line))
            ->map(fn (array $line): array => [
                'description' => $this->text($line['description'] ?? null),
                'quantity' => $this->decimal($line['quantity'] ?? null),
                'unit_price' => $this->decimal($line['unit_price'] ?? null),
                'vat_rate' => $this->decimal($line['vat_rate'] ?? null) ?? '0',
                'cash_category_id' => ($line['cash_category_id'] ?? null) ?: null,
                'stock_item_id' => ($line['stock_item_id'] ?? null) ?: null,
            ])
            ->reject(fn (array $line): bool => $line['description'] === null && $line['quantity'] === null && $line['unit_price'] === null && $line['stock_item_id'] === null)
            ->values()
            ->all();

        $direction = InvoiceDirection::tryFrom((string) $this->input('direction'));
        $recipient = $direction === InvoiceDirection::Sale
            ? (InvoiceRecipient::tryFrom((string) $this->input('recipient'))?->value ?? InvoiceRecipient::Party->value)
            : InvoiceRecipient::Party->value;
        $plate = $recipient === InvoiceRecipient::Plate->value
            ? mb_strtoupper(trim((string) $this->input('plate')), 'UTF-8')
            : null;
        $partyId = $this->input('party_id') ?: null;

        if ($recipient === InvoiceRecipient::Plate->value && $plate !== '' && $plate !== null && $this->input('issued_on')) {
            $partyId = Vehicle::findByPlate($plate)?->ownershipOn(Carbon::parse($this->string('issued_on')->value()))?->party_id;
        }

        $this->merge([
            'recipient' => $recipient,
            'party_id' => $partyId,
            'invoice_no' => $this->text($this->input('invoice_no')),
            'due_on' => $this->input('due_on') ?: null,
            'site_id' => $this->input('site_id') ?: null,
            'description' => $this->text($this->input('description')),
            'returns_invoice_id' => ($direction?->isReturn() ?? false)
                ? ($this->input('returns_invoice_id') ?: null)
                : null,
            'lines' => $lines,
        ]);

        if ($recipient === InvoiceRecipient::Plate->value) {
            $this->merge(['plate' => $plate ?: null]);
        } else {
            $this->request->remove('plate');
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->route('invoice');

        $plateSale = $this->isPlateSale();

        return [
            'direction' => ['required', Rule::enum(InvoiceDirection::class)],
            'recipient' => ['required', Rule::enum(InvoiceRecipient::class)],
            'returns_invoice_id' => [
                'nullable',
                'uuid',
                Rule::exists(Invoice::class, 'id')->withoutTrashed()
                    ->where('is_draft', false)
                    ->where('direction', InvoiceDirection::tryFrom((string) $this->input('direction'))?->returnTarget()?->value ?? '__none__'),
                Rule::prohibitedIf(fn (): bool => ! (InvoiceDirection::tryFrom((string) $this->input('direction'))?->isReturn() ?? false)),
            ],
            'party_id' => $plateSale
                ? ['required', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()]
                : ['required', 'uuid', Rule::exists(Party::class, 'id')->withoutTrashed()->whereNull('system_key')],
            'plate' => $plateSale
                ? ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Vehicle::findByPlate((string) $value)) {
                        $fail('Kayıtlı bir plaka girin.');
                    }
                }]
                : ['prohibited'],
            'invoice_no' => [
                'required',
                'string',
                'max:30',
                // Same uniqueness for drafts and live invoices (party + direction + number) so approve cannot collide.
                Rule::unique(Invoice::class, 'invoice_no')
                    ->withoutTrashed()
                    ->where('party_id', (string) $this->input('party_id'))
                    ->where('direction', (string) $this->input('direction'))
                    ->ignore($invoice?->id),
            ],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'site_id' => ['nullable', 'uuid', Rule::exists(Site::class, 'id')->withoutTrashed()],
            'description' => ['nullable', 'string', 'max:255'],
            'is_draft' => [$this->isCreating() ? 'required' : 'prohibited', 'boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'lines.*.vat_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'lines.*.cash_category_id' => ['nullable', 'uuid', Rule::exists(CashCategory::class, 'id')->withoutTrashed()],
            'lines.*.stock_item_id' => ['nullable', 'uuid', Rule::exists(StockItem::class, 'id')->withoutTrashed()],
            ...$this->attachmentRules(required: false),
            ...$this->entryPaymentRules(),
        ];
    }

    /**
     * Only a new invoice takes a payment status; "Ödendi" pays the whole total, so no amount is asked,
     * "Kısmen ödendi" pays the amount entered.
     *
     * @return array<string, array<mixed>>
     */
    private function entryPaymentRules(): array
    {
        if (! $this->isCreating() || $this->isDraft()) {
            return [];
        }

        if (InvoiceDirection::tryFrom((string) $this->input('direction'))?->isReturn() ?? false) {
            return [
                'payment_status' => ['required', Rule::in([InvoicePaymentStatus::Unpaid->value])],
            ];
        }

        return [
            'payment_status' => ['required', Rule::in(array_column(InvoicePaymentStatus::atEntry(), 'value'))],
            ...match ($this->entryPaymentStatus()) {
                InvoicePaymentStatus::Paid => Arr::except($this->invoicePaymentRules('payment.'), 'payment.amount'),
                InvoicePaymentStatus::Partial => $this->invoicePaymentRules('payment.'),
                default => [],
            },
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $invoice = $this->route('invoice');

                $this->checkAttachmentRoom($validator, $invoice instanceof Invoice ? $invoice : null);
            },
            function (Validator $validator): void {
                if (! $this->isPlateSale() || $validator->errors()->hasAny(['plate', 'issued_on', 'party_id'])) {
                    return;
                }

                $vehicle = Vehicle::findByPlate((string) $this->input('plate'));
                $date = Carbon::parse($this->string('issued_on')->value());

                if ($vehicle && ! $vehicle->ownershipOn($date)?->party_id) {
                    $validator->errors()->add('plate', sprintf(
                        'Bu aracın %s tarihinde kayıtlı sahibi yok. Araç kartından sahiplik geçmişini kontrol edin.',
                        $date->format('d.m.Y'),
                    ));
                }
            },
            function (Validator $validator): void {
                $direction = InvoiceDirection::tryFrom((string) $this->input('direction'));

                if ($direction === null) {
                    return;
                }

                $expects = $direction->categoryDirection();

                foreach ($this->input('lines', []) as $index => $line) {
                    $key = "lines.{$index}.cash_category_id";

                    if ($line['cash_category_id'] === null || $validator->errors()->has($key)) {
                        continue;
                    }

                    $category = CashCategory::query()->find($line['cash_category_id']);

                    if ($category && ! in_array($category->direction, [$expects, CashDirection::Both], true)) {
                        $validator->errors()->add($key, sprintf('%s faturasında %s kategorisi seçilmeli.', $direction->label(), mb_strtolower($expects->label(), 'UTF-8')));
                    }
                }
            },
            function (Validator $validator): void {
                if (! $this->paysAtEntry() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = app(InvoiceService::class)->total($this->input('lines'));

                if ($total <= 0) {
                    $validator->errors()->add('payment_status', 'Tutarı olmayan fatura ödenmiş olarak girilemez.');

                    return;
                }

                $this->checkInvoicePayment($validator, $total, 'payment.');
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'direction' => 'fatura türü',
            'recipient' => 'kime',
            'returns_invoice_id' => 'bağlı fatura',
            'party_id' => 'cari',
            'plate' => 'plaka',
            'invoice_no' => 'fatura no',
            'issued_on' => 'fatura tarihi',
            'due_on' => 'vade tarihi',
            'site_id' => 'şantiye',
            'description' => 'açıklama',
            'is_draft' => 'taslak',
            'lines' => 'fatura satırları',
            'lines.*.description' => 'satır açıklaması',
            'lines.*.quantity' => 'miktar',
            'lines.*.unit_price' => 'birim fiyat',
            'lines.*.vat_rate' => 'KDV oranı',
            'lines.*.cash_category_id' => 'kategori',
            'lines.*.stock_item_id' => 'stok kalemi',
            ...$this->attachmentAttributes(),
            'payment_status' => 'ödeme durumu',
            ...$this->invoicePaymentAttributes('payment.'),
            'payment.due_on' => 'çek / senet vadesi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_no.unique' => 'Bu carinin bu numarada bir faturası zaten var.',
            'lines.required' => 'En az bir fatura satırı girin.',
            ...$this->attachmentMessages(),
        ];
    }

    private function isCreating(): bool
    {
        return ! $this->route('invoice') instanceof Invoice;
    }

    private function isPlateSale(): bool
    {
        return InvoiceDirection::tryFrom((string) $this->input('direction')) === InvoiceDirection::Sale
            && InvoiceRecipient::tryFrom((string) $this->input('recipient')) === InvoiceRecipient::Plate;
    }

    private function isDraft(): bool
    {
        return $this->isCreating() && $this->boolean('is_draft');
    }

    private function entryPaymentStatus(): ?InvoicePaymentStatus
    {
        return $this->isCreating() && ! $this->isDraft()
            ? InvoicePaymentStatus::tryFrom((string) $this->input('payment_status'))
            : null;
    }

    private function paysAtEntry(): bool
    {
        return ! in_array($this->entryPaymentStatus(), [null, InvoicePaymentStatus::Unpaid], true);
    }

    private function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? null : $text;
    }

    private function decimal(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : str_replace(',', '.', $value);
    }
}

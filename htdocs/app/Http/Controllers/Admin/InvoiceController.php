<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceDirection;
use App\Http\Requests\Admin\InvoiceAttachmentRequest;
use App\Http\Requests\Admin\InvoiceMatchRequest;
use App\Http\Requests\Admin\InvoicePaymentRequest;
use App\Http\Requests\Admin\InvoiceRequest;
use App\Http\Requests\Admin\InvoiceSearchRequest;
use App\Models\Invoice;
use App\Models\InvoiceAttachment;
use App\Models\InvoicePayment;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends AdminController
{
    protected ?string $permissionResource = 'invoice';

    protected array $permissionMap = [
        'storeAttachments' => 'edit-invoice',
        'destroyAttachment' => 'edit-invoice',
        'storePayment' => ['edit-invoice', 'create-cari-entry'],
        'matchPayment' => 'edit-invoice',
        'distributePayments' => 'edit-invoice',
        'unmatchPayment' => 'edit-invoice',
        'dismissReminder' => 'edit-invoice',
        'approve' => 'edit-invoice',
        'vehicles' => 'view-invoice',
    ];

    public function __construct(
        private InvoiceService $invoices,
        private HaulLookupRepository $lookups,
    ) {}

    /**
     * Plate autocomplete for "Kime: Plaka" on sale invoices.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return response()->json([]);
        }

        $vehicles = $this->lookups->vehicles($term)->map(fn (Vehicle $vehicle) => [
            'value' => $vehicle->plate,
            'meta' => collect([
                $vehicle->currentOwnership?->party?->name,
                $vehicle->vehicleClass?->name,
            ])->filter()->implode(' · '),
        ]);

        return response()->json($vehicles);
    }

    public function index(InvoiceSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.invoices.index', [
            'title' => 'Faturalar',
            'invoices' => $this->invoices->paginate($filters, $request->sort()),
            'filters' => $filters,
            'options' => $this->invoices->searchOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.invoices.create', [
            'title' => 'Yeni fatura',
            'lines' => [],
            'options' => $this->invoices->formOptions(),
            'paymentOptions' => $this->invoices->entryPaymentOptions(),
        ]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $draft = (bool) ($request->validated('is_draft') ?? false);

        return $this->complete(
            fn () => $this->invoices->create($request->validated(), $request->user()?->id),
            'admin.invoices.index',
            $draft ? 'Taslak fatura kaydedildi.' : 'Fatura kaydedildi.',
        );
    }

    public function show(Invoice $invoice): View
    {
        return view('admin.invoices.show', [
            'title' => 'Fatura detayı',
            'invoice' => $invoice->load(['party', 'vehicle', 'site', 'creator', 'lines.cashCategory', 'lines.stockItem', 'attachments', 'payments.payment', 'returnedInvoice']),
            'ledgerEntry' => $this->invoices->ledgerEntry($invoice),
            'paymentOptions' => $this->invoices->paymentOptions(),
            'unmatchedPayments' => $this->invoices->unmatchedPayments($invoice),
        ]);
    }

    public function storePayment(InvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->pay($invoice, $request->validated(), $request->user()?->id),
            'admin.invoices.show',
            $invoice->direction->paymentLabel().' kaydedildi.',
            [$invoice],
        );
    }

    public function matchPayment(InvoiceMatchRequest $request, Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->matchPayment($invoice, $request->validated(), $request->user()?->id),
            'admin.invoices.show',
            $invoice->direction->paymentLabel().' faturaya bağlandı.',
            [$invoice],
        );
    }

    public function distributePayments(Request $request, Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->distributePayments($invoice, $request->user()?->id),
            'admin.invoices.show',
            $invoice->direction === InvoiceDirection::Purchase ? 'Boşta kalan ödemeler faturaya dağıtıldı.' : 'Boşta kalan tahsilatlar faturaya dağıtıldı.',
            [$invoice],
        );
    }

    public function unmatchPayment(Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->unmatchPayment($payment),
            'admin.invoices.show',
            'Bağlantı kaldırıldı.',
            [$invoice],
        );
    }

    public function edit(Invoice $invoice): View
    {
        $invoice->load(['lines', 'vehicle'])->loadCount('attachments');

        return view('admin.invoices.edit', [
            'title' => 'Faturayı düzenle',
            'invoice' => $invoice,
            'lines' => $this->invoices->formLines($invoice),
            'options' => $this->invoices->formOptions($invoice),
        ]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->update($invoice, $request->validated(), $request->user()?->id),
            'admin.invoices.index',
            $invoice->isDraft() ? 'Taslak fatura güncellendi.' : 'Fatura güncellendi.',
        );
    }

    public function approve(Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->approve($invoice),
            'admin.invoices.show',
            'Fatura onaylandı; cariye yazıldı.',
            [$invoice],
        );
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->delete($invoice),
            'admin.invoices.index',
            $invoice->isDraft() ? 'Taslak fatura silindi.' : 'Fatura silindi.',
        );
    }

    public function storeAttachments(InvoiceAttachmentRequest $request, Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->addAttachments($invoice, $request->validated('attachments'), $request->user()?->id),
            'admin.invoices.show',
            'Dosya eklendi.',
            [$invoice],
        );
    }

    public function showAttachment(Invoice $invoice, InvoiceAttachment $attachment): BinaryFileResponse
    {
        $file = $this->invoices->attachmentFile($attachment);

        abort_unless(is_file($file['path']), 404);

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => sprintf('inline; filename="fatura-%s.%s"', Str::slug($invoice->invoice_no), pathinfo($file['path'], PATHINFO_EXTENSION)),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyAttachment(Invoice $invoice, InvoiceAttachment $attachment): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->deleteAttachment($attachment),
            'admin.invoices.show',
            'Dosya silindi.',
            [$invoice],
        );
    }

    public function dismissReminder(Invoice $invoice): RedirectResponse
    {
        return $this->complete(
            fn () => $this->invoices->dismissReminder($invoice),
            'admin.dashboard',
            'Hatırlatma kapatıldı.',
        );
    }
}

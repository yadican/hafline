<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Http\Requests\Admin\FinancialInstrumentMovementRequest;
use App\Http\Requests\Admin\FinancialInstrumentPhotoRequest;
use App\Http\Requests\Admin\FinancialInstrumentRequest;
use App\Http\Requests\Admin\FinancialInstrumentSearchRequest;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentPhoto;
use App\Repositories\PartyRepository;
use App\Services\CariEntryService;
use App\Services\FinancialInstrumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinancialInstrumentController extends AdminController
{
    protected ?string $permissionResource = 'financial-instrument';

    protected array $permissionMap = [
        'storeMovement' => 'edit-financial-instrument',
        'undoMovement' => 'delete-financial-instrument',
        'storePhotos' => 'edit-financial-instrument',
        'destroyPhoto' => 'edit-financial-instrument',
    ];

    public function __construct(
        private FinancialInstrumentService $instruments,
        private CariEntryService $entries,
        private PartyRepository $parties,
    ) {}

    public function index(FinancialInstrumentSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.financial-instruments.index', [
            'title' => 'Çek / Senet',
            'instruments' => $this->instruments->paginate($filters, $request->sort()),
            'totals' => $this->instruments->openTotals($filters),
            'filters' => $filters,
            'methods' => [PaymentMethod::Cheque->value => PaymentMethod::Cheque->label(), PaymentMethod::PromissoryNote->value => PaymentMethod::PromissoryNote->label()],
            'directions' => InstrumentDirection::options(),
            'statuses' => InstrumentStatus::options(),
            'parties' => $this->parties->options(include: $filters['party'] ?? null),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create-cari-entry');
        $groups = [];

        foreach (array_keys(FinancialInstrumentRequest::kinds()) as $role) {
            $role = PartyRole::from($role);
            $groups[$role->value] = ['label' => $role->label(), 'parties' => $this->parties->withoutSystem($this->parties->options($role))];
        }

        return view('admin.financial-instruments.create', [
            'title' => 'Yeni çek / senet',
            'groups' => $groups,
            'methods' => [PaymentMethod::Cheque->value => PaymentMethod::Cheque->label(), PaymentMethod::PromissoryNote->value => PaymentMethod::PromissoryNote->label()],
            'directions' => InstrumentDirection::options(),
            'form' => [
                'direction' => InstrumentDirection::Received->value,
                'payment_method' => PaymentMethod::Cheque->value,
                'occurred_on' => today()->toDateString(),
            ],
        ]);
    }

    public function store(FinancialInstrumentRequest $request): RedirectResponse
    {
        return $this->complete(
            fn () => $this->entries->create($request->cariEntry(), $request->user()?->id),
            'admin.financial-instruments.index',
            'Çek / senet kaydedildi; cari hareketi de oluşturuldu.',
        );
    }

    public function show(FinancialInstrument $financialInstrument): View
    {
        $financialInstrument->load(['party', 'movements.bank', 'movements.party', 'photos']);

        return view('admin.financial-instruments.show', [
            'title' => 'Çek / senet detayı',
            'instrument' => $financialInstrument,
            'source' => $this->instruments->sourceEntry($financialInstrument),
            'options' => $this->instruments->actionOptions($financialInstrument),
        ]);
    }

    public function storeMovement(FinancialInstrumentMovementRequest $request, FinancialInstrument $financialInstrument): RedirectResponse
    {
        return $this->complete(
            fn () => $this->instruments->record($financialInstrument, $request->validated(), $request->user()?->id),
            'admin.financial-instruments.show',
            'İşlem kaydedildi.',
            [$financialInstrument],
        );
    }

    public function storePhotos(FinancialInstrumentPhotoRequest $request, FinancialInstrument $financialInstrument): RedirectResponse
    {
        return $this->complete(
            fn () => $this->instruments->addPhotos($financialInstrument, $request->validated('photos'), $request->user()?->id),
            'admin.financial-instruments.show',
            'Resim eklendi.',
            [$financialInstrument],
        );
    }

    public function showPhoto(FinancialInstrument $financialInstrument, FinancialInstrumentPhoto $photo): BinaryFileResponse
    {
        $file = $this->instruments->photoFile($photo);

        abort_unless(is_file($file['path']), 404);

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyPhoto(FinancialInstrument $financialInstrument, FinancialInstrumentPhoto $photo): RedirectResponse
    {
        return $this->complete(
            fn () => $this->instruments->deletePhoto($photo),
            'admin.financial-instruments.show',
            'Resim silindi.',
            [$financialInstrument],
        );
    }

    public function undoMovement(FinancialInstrument $financialInstrument): RedirectResponse
    {
        return $this->complete(
            fn () => $this->instruments->undoLast($financialInstrument),
            'admin.financial-instruments.show',
            'Son işlem geri alındı.',
            [$financialInstrument],
        );
    }
}

<?php

namespace App\Services\Api;

use App\Enums\Shift;
use App\Enums\TicketKind;
use App\Models\FieldHaulSession;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\HaulScan;
use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\HaulLookupRepository;
use App\Services\Api\Ocr\PlateScanClient;
use App\Services\FuelReceiptService;
use App\Services\Service;
use App\Services\TicketNumbers;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Fuel receipts from the field app. Site and shift come from the open field session, the receipt
 * date is today; price, owner and ledger follow the admin rules in FuelReceiptService.
 */
class FieldFuelReceiptService extends Service
{
    public function __construct(
        private FuelReceiptService $receipts,
        private FieldHaulService $fieldHauls,
        private HaulLookupRepository $lookups,
        private SecureImageIngest $images,
        private PlateScanClient $ocr,
        private TicketNumbers $ticketNumbers,
    ) {}

    /**
     * Active stations serving the session's site (stations without linked sites serve every site).
     *
     * @return array{stations: list<array{id: string, name: string, firm_name: ?string, unit_price: string}>}
     */
    public function options(User $user): array
    {
        $session = $this->requireCurrentSession($user);

        $stations = FuelStation::query()
            ->with(['party', 'sites'])
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (FuelStation $station): bool => $this->servesSite($station, $session['site_id']))
            ->map(fn (FuelStation $station): array => [
                'id' => $station->id,
                'name' => $station->name,
                'firm_name' => $station->party?->name,
                'unit_price' => $station->unit_price,
            ])
            ->values()
            ->all();

        return compact('stations');
    }

    public function servesSite(FuelStation $station, ?string $siteId): bool
    {
        $station->loadMissing('sites');

        return $station->sites->isEmpty() || $station->sites->contains('id', $siteId);
    }

    /**
     * Active vehicles whose plate contains the term (at least two characters).
     *
     * @return list<array{plate: string, owner_name: ?string, class_name: ?string}>
     */
    public function vehicles(string $term): array
    {
        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return [];
        }

        return $this->lookups->vehicles($term)
            ->map(fn (Vehicle $vehicle): array => [
                'plate' => $vehicle->plate,
                'owner_name' => $vehicle->currentOwnership?->party?->name,
                'class_name' => $vehicle->vehicleClass?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Whether a receipt can be written for this plate on the receipt date (today for new receipts).
     *
     * @return array{
     *     vehicle: array{id: ?string, plate: ?string, found: bool},
     *     owner_on_date: bool,
     *     owner_name: ?string,
     *     can_create: bool,
     *     message: ?string
     * }
     */
    public function checkPlate(?string $plate, ?FuelReceipt $receipt = null): array
    {
        $date = $this->issueDate($receipt);
        $vehicle = $plate ? Vehicle::findByPlate($plate) : null;
        $owner = $vehicle?->ownershipOn($date)?->party;
        $problem = $vehicle ? $this->plateProblem($vehicle, $date) : 'Kayıtlı araç bulunamadı.';

        return [
            'vehicle' => [
                'id' => $vehicle?->id,
                'plate' => $vehicle?->plate,
                'found' => $vehicle !== null,
            ],
            'owner_on_date' => $owner !== null,
            'owner_name' => $owner?->name,
            'can_create' => $problem === null,
            'message' => $problem,
        ];
    }

    public function plateProblem(Vehicle $vehicle, CarbonInterface $date): ?string
    {
        if (! $this->receipts->ownerOn($vehicle, $date)) {
            return sprintf('Bu aracın %s tarihinde kayıtlı sahibi yok.', $date->format('d.m.Y'));
        }

        return null;
    }

    public function issueDate(?FuelReceipt $receipt = null): CarbonInterface
    {
        return $receipt ? $receipt->issued_on->copy()->startOfDay() : Carbon::today();
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(UploadedFile $file, string $fieldSessionId, User $user): array
    {
        $session = $this->openSession($user, $fieldSessionId);

        // OCR the original upload — ingest downscales/re-encodes and can corrupt plate glyphs (E→K).
        $this->images->assertAcceptable($file);
        $ocr = $this->ocr->scan($file->getRealPath());
        $stored = $this->images->ingest($file, $session->id);

        $scan = HaulScan::query()->create([
            'user_id' => $user->id,
            'field_haul_session_id' => $session->id,
            'path' => $stored['path'],
            'checksum' => $stored['checksum'],
            'status' => HaulScan::STATUS_PENDING,
            'plate' => $ocr['plate'],
            'confidence' => $ocr['confidence'],
            'expires_at' => now()->addHour(),
        ]);

        $check = $this->checkPlate($ocr['plate']);

        return [
            'success' => $ocr['success'],
            'scan_id' => $scan->id,
            'field_session_id' => $session->id,
            'plate' => $ocr['plate'],
            'confidence' => $ocr['confidence'],
            'matched_regex' => $ocr['matched_regex'],
            ...$check,
            'can_create' => $ocr['success'] && $check['can_create'],
            'message' => $ocr['success'] ? $check['message'] : ($ocr['message'] ?? 'Plaka okunamadı.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(array $data, User $user): array
    {
        $session = $this->openSession($user, (string) $data['field_session_id']);
        $scan = empty($data['scan_id']) ? null : $this->pendingScan($user, $session, (string) $data['scan_id']);
        $shift = $session->shift instanceof Shift ? $session->shift->value : (string) $session->shift;

        $receipt = $this->receipts->create([
            ...$this->editable($data),
            'ticket_no' => $this->ticketNumbers->next(TicketKind::FuelReceipt),
            'issued_on' => Carbon::today()->toDateString(),
            'site_id' => $session->site_id,
            'shift' => $shift,
        ], $user->id);

        if ($scan) {
            $scan->update([
                'fuel_receipt_id' => $receipt->id,
                'path' => $this->images->moveAttached($scan->path, $receipt->id, 'fuel-photos'),
                'status' => HaulScan::STATUS_ATTACHED,
            ]);
        }

        return $this->printPayload($receipt);
    }

    /**
     * Ticket number, date, site and shift stay as they were; the rest follows the admin form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $user, FuelReceipt $receipt, array $data): array
    {
        $this->assertCanView($user, $receipt);

        $updated = $this->receipts->update($receipt, [
            ...$this->editable($data),
            'ticket_no' => $receipt->ticket_no,
            'issued_on' => $receipt->issued_on->toDateString(),
            'site_id' => $receipt->site_id,
            'shift' => $receipt->shift?->value,
        ]);

        return $this->printPayload($updated);
    }

    public function delete(User $user, FuelReceipt $receipt): void
    {
        $this->assertCanView($user, $receipt);

        $this->receipts->delete($receipt);
    }

    /**
     * Today's fuel receipts for the locked site / shift (requires an open field session).
     *
     * @return array{session: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function sessionReceipts(User $user): array
    {
        $session = $this->requireCurrentSession($user);

        $receipts = FuelReceipt::query()
            ->with(['vehicle', 'fuelStation', 'site'])
            ->where('created_by', $user->id)
            ->where('site_id', $session['site_id'])
            ->where('shift', $session['shift'])
            ->whereDate('issued_on', Carbon::today())
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (FuelReceipt $receipt): array => [
                'id' => $receipt->id,
                'ticket_no' => $receipt->ticket_no,
                'plate' => $receipt->vehicle?->plate,
                'issued_on_label' => $receipt->issued_on->format('d/m/Y'),
                'site_name' => $receipt->site?->name,
                'shift' => $session['shift'],
                'station_name' => $receipt->fuelStation?->name,
                'quantity' => (float) $receipt->quantity,
                'amount' => $receipt->total(),
            ])
            ->all();

        return [
            'session' => $session,
            'data' => $receipts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showFor(User $user, FuelReceipt $receipt): array
    {
        $this->assertCanView($user, $receipt);

        return $this->printPayload($receipt);
    }

    /**
     * The creator sees the receipt while their open session has its site and shift; `yonetici` sees every receipt.
     */
    public function assertCanView(User $user, FuelReceipt $receipt): void
    {
        if ($user->hasRole('yonetici', 'web')) {
            return;
        }

        if ($receipt->created_by !== $user->id) {
            abort(404);
        }

        $session = $this->fieldHauls->currentSession($user);

        if (! $session || $receipt->site_id !== $session['site_id'] || $receipt->shift?->value !== $session['shift']) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCurrentSession(User $user): array
    {
        $session = $this->fieldHauls->currentSession($user);

        if (! $session) {
            throw ValidationException::withMessages([
                'field_session_id' => 'Önce şantiye ve vardiyayı seçin.',
            ]);
        }

        return $session;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function editable(array $data): array
    {
        return [
            'plate' => $data['plate'],
            'fuel_station_id' => $data['fuel_station_id'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'] ?? null,
            'km' => $data['km'] ?? null,
            'received_by' => $data['received_by'] ?? null,
            'haul_ticket_count' => $data['haul_ticket_count'] ?? null,
        ];
    }

    private function openSession(User $user, string $fieldSessionId): FieldHaulSession
    {
        $session = FieldHaulSession::query()
            ->whereKey($fieldSessionId)
            ->where('user_id', $user->id)
            ->open()
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'field_session_id' => 'Geçerli bir saha oturumu seçin veya yeniden sabitleyin.',
            ]);
        }

        return $session;
    }

    private function pendingScan(User $user, FieldHaulSession $session, string $scanId): HaulScan
    {
        $scan = HaulScan::query()
            ->whereKey($scanId)
            ->where('user_id', $user->id)
            ->where('field_haul_session_id', $session->id)
            ->first();

        if (! $scan || ! $scan->isPending() || ! $scan->isUnattached()) {
            throw ValidationException::withMessages([
                'scan_id' => 'Geçersiz veya süresi dolmuş tarama. Yeni fotoğraf çekin ya da plakayı elle girin.',
            ]);
        }

        return $scan;
    }

    /**
     * @return array<string, mixed>
     */
    private function printPayload(FuelReceipt $receipt): array
    {
        $receipt->load(['vehicle.vehicleClass', 'party', 'site', 'fuelStation', 'fuelParty']);

        return [
            'id' => $receipt->id,
            'title' => 'YAKIT FİŞİ',
            'ticket_no' => $receipt->ticket_no,
            'issued_on' => $receipt->issued_on->toDateString(),
            'issued_on_label' => $receipt->issued_on->format('d/m/Y'),
            'plate' => $receipt->vehicle?->plate,
            'class_name' => $receipt->vehicle?->vehicleClass?->name,
            'owner_name' => $receipt->party?->name,
            'site_name' => $receipt->site?->name,
            'shift' => $receipt->shift?->value,
            'shift_label' => $receipt->shift?->label(),
            'station' => $receipt->fuelStation ? ['id' => $receipt->fuelStation->id, 'name' => $receipt->fuelStation->name] : null,
            'firm_name' => $receipt->fuelParty?->name,
            'quantity' => (float) $receipt->quantity,
            'quantity_label' => FuelReceipt::formatQuantity($receipt->quantity),
            'unit_price' => $receipt->unit_price,
            'amount' => $receipt->total(),
            'km' => $receipt->km,
            'received_by' => $receipt->received_by,
            'haul_ticket_count' => $receipt->haul_ticket_count,
            'qr_payload' => $receipt->id,
            'photo_url' => $this->photoUrlFor($receipt),
        ];
    }

    private function photoUrlFor(FuelReceipt $receipt): ?string
    {
        $path = HaulScan::query()
            ->where('fuel_receipt_id', $receipt->id)
            ->where('status', HaulScan::STATUS_ATTACHED)
            ->value('path');

        return is_string($path) && $path !== '' ? $this->images->url($path) : null;
    }
}

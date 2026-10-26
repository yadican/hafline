<?php

namespace App\Services\Api;

use App\Enums\Shift;
use App\Enums\TicketKind;
use App\Models\FieldHaulSession;
use App\Models\HaulScan;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Api\Ocr\PlateScanClient;
use App\Services\MachineWorkService;
use App\Services\Service;
use App\Services\TicketNumbers;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Machine work tickets from the field app. Site and shift come from the open field session,
 * the work date is today; rates, owner and ledger follow the admin rules in MachineWorkService.
 */
class FieldMachineWorkService extends Service
{
    public function __construct(
        private MachineWorkService $works,
        private FieldHaulService $fieldHauls,
        private SecureImageIngest $images,
        private PlateScanClient $ocr,
        private TicketNumbers $ticketNumbers,
    ) {}

    /**
     * @return array{operators: list<array{id: string, name: string, salaried: bool, last_rate: ?string}>}
     */
    public function options(?MachineWork $work = null): array
    {
        $names = $this->works->operatorOptions($work);
        $parties = Party::withTrashed()->whereIn('id', array_keys($names))->get()->keyBy('id');

        $operators = collect($names)
            ->map(function (string $name, string $id) use ($parties): array {
                /** @var Party|null $party */
                $party = $parties->get($id);
                $salaried = $party !== null && $this->works->isSalaried($party);

                return [
                    'id' => $id,
                    'name' => $name,
                    'salaried' => $salaried,
                    'last_rate' => $party && ! $salaried ? $this->works->lastOperatorRate($party) : null,
                ];
            })
            ->values()
            ->all();

        return compact('operators');
    }

    /**
     * Active hourly machines whose plate contains the term (at least two characters).
     *
     * @return list<array{plate: string, owner_name: ?string, class_name: ?string, hourly_rate: ?string}>
     */
    public function machines(string $term): array
    {
        if (mb_strlen(Vehicle::normalizePlate($term)) < 2) {
            return [];
        }

        return $this->works->machines($term)
            ->map(fn (Vehicle $vehicle): array => [
                'plate' => $vehicle->plate,
                'owner_name' => $vehicle->currentOwnership?->party?->name,
                'class_name' => $vehicle->vehicleClass?->name,
                'hourly_rate' => $vehicle->currentRate?->hourly_rate,
            ])
            ->values()
            ->all();
    }

    /**
     * Whether a ticket can be written for this plate on the work date (today for new tickets).
     *
     * @return array{
     *     vehicle: array{id: ?string, plate: ?string, found: bool},
     *     hourly: bool,
     *     owner_on_date: bool,
     *     owner_name: ?string,
     *     hourly_rate: ?string,
     *     rate_required: bool,
     *     can_create: bool,
     *     message: ?string
     * }
     */
    public function checkPlate(?string $plate, ?MachineWork $work = null): array
    {
        $date = $this->workDate($work);
        $vehicle = $plate ? Vehicle::findByPlate($plate) : null;
        $owner = $vehicle?->ownershipOn($date)?->party;
        $rate = $vehicle ? $this->works->rateOn($vehicle, $date) : null;
        $problem = $vehicle ? $this->plateProblem($vehicle, $date, $work) : 'Kayıtlı araç bulunamadı.';
        $rateRequired = $vehicle !== null && $rate === null;

        return [
            'vehicle' => [
                'id' => $vehicle?->id,
                'plate' => $vehicle?->plate,
                'found' => $vehicle !== null,
            ],
            'hourly' => (bool) $vehicle?->isHourly(),
            'owner_on_date' => $owner !== null,
            'owner_name' => $owner?->name,
            'hourly_rate' => $rate,
            'rate_required' => $rateRequired,
            'can_create' => $problem === null,
            'message' => $problem ?? ($rateRequired ? 'Bu makine için saat ücreti tanımlı değil; ücreti girin.' : null),
        ];
    }

    /**
     * The reason a ticket cannot be written for this machine on this date. A ticket that already
     * belongs to the machine keeps it even if its class is no longer hourly.
     */
    public function plateProblem(Vehicle $vehicle, CarbonInterface $date, ?MachineWork $work = null): ?string
    {
        if (! $vehicle->isHourly() && $vehicle->id !== $work?->vehicle_id) {
            return 'Bu araç saatlik çalışan bir tipte değil. Araç tipinde "Saatlik çalışır" işaretli olmalı.';
        }

        if (! $this->works->ownerOn($vehicle, $date)) {
            return sprintf('Bu aracın %s tarihinde kayıtlı sahibi yok.', $date->format('d.m.Y'));
        }

        return null;
    }

    public function workDate(?MachineWork $work = null): CarbonInterface
    {
        return $work ? $work->worked_on->copy()->startOfDay() : Carbon::today();
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

        $work = $this->works->create([
            ...$this->editable($data),
            'ticket_no' => $this->ticketNumbers->next(TicketKind::MachineWork),
            'worked_on' => Carbon::today()->toDateString(),
            'site_id' => $session->site_id,
            'shift' => $shift,
        ], $user->id);

        if ($scan) {
            $scan->update([
                'machine_work_id' => $work->id,
                'path' => $this->images->moveAttached($scan->path, $work->id, 'machine-photos'),
                'status' => HaulScan::STATUS_ATTACHED,
            ]);
        }

        return $this->printPayload($work);
    }

    /**
     * Ticket number, date, site and shift stay as they were; the rest follows the admin form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $user, MachineWork $work, array $data): array
    {
        $this->assertCanView($user, $work);

        $updated = $this->works->update($work, [
            ...$this->editable($data),
            'ticket_no' => $work->ticket_no,
            'worked_on' => $work->worked_on->toDateString(),
            'site_id' => $work->site_id,
            'shift' => $work->shift instanceof Shift ? $work->shift->value : (string) $work->shift,
        ]);

        return $this->printPayload($updated);
    }

    public function delete(User $user, MachineWork $work): void
    {
        $this->assertCanView($user, $work);

        $this->works->delete($work);
    }

    /**
     * Today's machine work tickets for the locked site / shift (requires an open field session).
     *
     * @return array{session: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function sessionWorks(User $user): array
    {
        $session = $this->fieldHauls->currentSession($user);

        if (! $session) {
            throw ValidationException::withMessages([
                'field_session_id' => 'Önce şantiye, döküm yeri ve vardiya oturumunu sabitleyin.',
            ]);
        }

        $works = MachineWork::query()
            ->with(['vehicle', 'operator', 'site'])
            ->where('created_by', $user->id)
            ->where('site_id', $session['site_id'])
            ->where('shift', $session['shift'])
            ->whereDate('worked_on', Carbon::today())
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (MachineWork $work): array => [
                'id' => $work->id,
                'ticket_no' => $work->ticket_no,
                'plate' => $work->vehicle?->plate,
                'worked_on_label' => $work->worked_on->format('d/m/Y'),
                'site_name' => $work->site?->name,
                'shift' => $session['shift'],
                'operator_name' => $work->operator?->name,
                'hours' => (float) $work->hours,
                'amount' => $work->total(),
            ])
            ->all();

        return [
            'session' => $session,
            'data' => $works,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showFor(User $user, MachineWork $work): array
    {
        $this->assertCanView($user, $work);

        return $this->printPayload($work);
    }

    /**
     * The creator sees the ticket while their open session has its site and shift; `yonetici` sees every ticket.
     */
    public function assertCanView(User $user, MachineWork $work): void
    {
        if ($user->hasRole('yonetici', 'web')) {
            return;
        }

        if ($work->created_by !== $user->id) {
            abort(404);
        }

        $session = $this->fieldHauls->currentSession($user);
        $shift = $work->shift instanceof Shift ? $work->shift->value : (string) $work->shift;

        if (! $session || $work->site_id !== $session['site_id'] || $shift !== $session['shift']) {
            abort(404);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function editable(array $data): array
    {
        return [
            'plate' => $data['plate'],
            'hours' => $data['hours'],
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'operator_id' => $data['operator_id'] ?? null,
            'operator_rate' => $data['operator_rate'] ?? null,
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
    private function printPayload(MachineWork $work): array
    {
        $work->load(['vehicle.vehicleClass', 'party', 'site', 'operator']);
        $shift = $work->shift instanceof Shift ? $work->shift : Shift::from((string) $work->shift);

        return [
            'id' => $work->id,
            'title' => 'MAKİNE ÇALIŞMA FİŞİ',
            'ticket_no' => $work->ticket_no,
            'worked_on' => $work->worked_on->toDateString(),
            'worked_on_label' => $work->worked_on->format('d/m/Y'),
            'plate' => $work->vehicle?->plate,
            'class_name' => $work->vehicle?->vehicleClass?->name,
            'owner_name' => $work->party?->name,
            'site_name' => $work->site?->name,
            'shift' => $shift->value,
            'shift_label' => $shift->label(),
            'hours' => (float) $work->hours,
            'hours_label' => MachineWork::formatHours($work->hours),
            'hourly_rate' => $work->hourly_rate,
            'amount' => $work->total(),
            'operator' => $work->operator ? ['id' => $work->operator->id, 'name' => $work->operator->name] : null,
            'operator_rate' => $work->operator_rate,
            'operator_amount' => $work->operatorTotal(),
            'qr_payload' => $work->id,
            'photo_url' => $this->photoUrlFor($work),
        ];
    }

    private function photoUrlFor(MachineWork $work): ?string
    {
        $path = HaulScan::query()
            ->where('machine_work_id', $work->id)
            ->where('status', HaulScan::STATUS_ATTACHED)
            ->value('path');

        return is_string($path) && $path !== '' ? $this->images->url($path) : null;
    }
}

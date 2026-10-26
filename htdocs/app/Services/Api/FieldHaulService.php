<?php

namespace App\Services\Api;

use App\Enums\Shift;
use App\Models\FieldHaulSession;
use App\Models\Haul;
use App\Models\HaulScan;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Api\Ocr\PlateScanClient;
use App\Services\HaulService;
use App\Services\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FieldHaulService extends Service
{
    public function __construct(
        private HaulService $hauls,
        private SecureImageIngest $images,
        private PlateScanClient $ocr,
    ) {}

    /**
     * @return array{sites: list<array{id: string, name: string}>, shifts: list<array{value: string, label: string}>}
     */
    public function options(): array
    {
        $sites = collect($this->hauls->formOptions(null)['sites'])
            ->map(fn (string $name, string $id): array => ['id' => $id, 'name' => $name])
            ->values()
            ->all();

        $shifts = collect(Shift::options())
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();

        return compact('sites', 'shifts');
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function dumpSites(string $siteId): array
    {
        return collect($this->hauls->dumpSitesFor($siteId))
            ->map(fn (string $name, string $id): array => ['id' => $id, 'name' => $name])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     success: bool,
     *     scan_id: string,
     *     field_session_id: string,
     *     plate: ?string,
     *     confidence: ?float,
     *     matched_regex: bool,
     *     vehicle: array{id: ?string, plate: ?string, found: bool},
     *     owner_on_date: bool,
     *     can_create: bool,
     *     message: ?string
     * }
     */
    public function scan(UploadedFile $file, string $fieldSessionId, User $user): array
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

        // OCR the original upload — ingest downscales/re-encodes and can corrupt plate glyphs (E→K).
        $this->images->assertAcceptable($file);
        $ocr = $this->ocr->scan($file->getRealPath());
        $stored = $this->images->ingest($file, $session->id);

        $plate = $ocr['plate'];
        $vehicle = $plate ? Vehicle::findByPlate($plate) : null;
        $ownerOnDate = $vehicle ? $this->hauls->ownerOn($vehicle, now()) !== null : false;
        $canCreate = $ocr['success'] && $vehicle !== null && $ownerOnDate;

        $message = null;

        if (! $ocr['success']) {
            $message = $ocr['message'] ?? 'Plaka okunamadı.';
        } elseif ($vehicle === null) {
            $message = 'Kayıtlı araç bulunamadı.';
        } elseif (! $ownerOnDate) {
            $message = 'Bu aracın bugün kayıtlı sahibi yok.';
        }

        $scan = HaulScan::query()->create([
            'user_id' => $user->id,
            'field_haul_session_id' => $session->id,
            'path' => $stored['path'],
            'checksum' => $stored['checksum'],
            'status' => HaulScan::STATUS_PENDING,
            'plate' => $plate,
            'confidence' => $ocr['confidence'],
            'expires_at' => now()->addHour(),
        ]);

        return [
            'success' => $ocr['success'],
            'scan_id' => $scan->id,
            'field_session_id' => $session->id,
            'plate' => $plate,
            'confidence' => $ocr['confidence'],
            'matched_regex' => $ocr['matched_regex'],
            'vehicle' => [
                'id' => $vehicle?->id,
                'plate' => $vehicle?->plate,
                'found' => $vehicle !== null,
            ],
            'owner_on_date' => $ownerOnDate,
            'can_create' => $canCreate,
            'message' => $message,
        ];
    }

    /**
     * @param  array{site_id: string, dump_site_id: string, shift: string}  $data
     * @return array<string, mixed>
     */
    public function lockSession(array $data, User $user): array
    {
        return $this->transaction(function () use ($data, $user): array {
            FieldHaulSession::query()
                ->where('user_id', $user->id)
                ->open()
                ->update(['closed_at' => now()]);

            /** @var FieldHaulSession $session */
            $session = FieldHaulSession::query()->create([
                'user_id' => $user->id,
                'site_id' => $data['site_id'],
                'dump_site_id' => $data['dump_site_id'],
                'shift' => $data['shift'],
                'locked_at' => now(),
            ]);

            $session->load(['site', 'dumpSite']);

            return $this->sessionPayload($session);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentSession(User $user): ?array
    {
        $session = FieldHaulSession::query()
            ->with(['site', 'dumpSite'])
            ->where('user_id', $user->id)
            ->open()
            ->latest('locked_at')
            ->first();

        return $session ? $this->sessionPayload($session) : null;
    }

    public function closeSession(User $user): void
    {
        FieldHaulSession::query()
            ->where('user_id', $user->id)
            ->open()
            ->update(['closed_at' => now()]);
    }

    /**
     * @param  array{plate: string, scan_id: string, field_session_id: string, tonnage: int}  $data
     * @return array<string, mixed>
     */
    public function store(array $data, User $user): array
    {
        $session = FieldHaulSession::query()
            ->whereKey($data['field_session_id'])
            ->where('user_id', $user->id)
            ->open()
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'field_session_id' => 'Geçerli bir saha oturumu seçin veya yeniden sabitleyin.',
            ]);
        }

        $scan = HaulScan::query()
            ->whereKey($data['scan_id'])
            ->where('user_id', $user->id)
            ->where('field_haul_session_id', $session->id)
            ->first();

        if (! $scan || ! $scan->isPending() || $scan->haul_id !== null) {
            throw ValidationException::withMessages([
                'scan_id' => 'Geçersiz veya süresi dolmuş tarama. Yeni fotoğraf çekin.',
            ]);
        }

        $ticketNo = $this->nextTicketNo();
        $shift = $session->shift instanceof Shift ? $session->shift->value : (string) $session->shift;

        /** @var Collection<int, Haul> $created */
        $created = $this->hauls->create([
            'ticket_no' => $ticketNo,
            'plate' => $data['plate'],
            'trips' => 1,
            'hauled_at' => now(),
            'site_id' => $session->site_id,
            'dump_site_id' => $session->dump_site_id,
            'tonnage' => (int) $data['tonnage'],
            'shift' => $shift,
        ], $user->id);

        /** @var Haul $haul */
        $haul = $created->first();
        $haul->update(['field_haul_session_id' => $session->id]);
        $haul->load(['site', 'dumpSite', 'vehicle']);

        $path = $this->images->moveAttached($scan->path, $haul->id);

        $scan->update([
            'haul_id' => $haul->id,
            'path' => $path,
            'status' => HaulScan::STATUS_ATTACHED,
        ]);

        return $this->printPayload($haul);
    }

    /**
     * Today's hauls for the locked site / dump / shift (requires an open field session).
     *
     * @return array{session: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function sessionHauls(User $user): array
    {
        $session = FieldHaulSession::query()
            ->with(['site', 'dumpSite'])
            ->where('user_id', $user->id)
            ->open()
            ->latest('locked_at')
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'field_session_id' => 'Önce şantiye, döküm yeri ve vardiya oturumunu sabitleyin.',
            ]);
        }

        $shift = $session->shift instanceof Shift ? $session->shift->value : (string) $session->shift;

        $hauls = Haul::query()
            ->with(['site', 'dumpSite', 'vehicle'])
            ->where('created_by', $user->id)
            ->where('site_id', $session->site_id)
            ->where('dump_site_id', $session->dump_site_id)
            ->where('shift', $shift)
            ->where('hauled_at', '>=', Carbon::today())
            ->where('hauled_at', '<', Carbon::tomorrow())
            ->orderByDesc('hauled_at')
            ->limit(100)
            ->get()
            ->map(fn (Haul $haul): array => [
                'id' => $haul->id,
                'ticket_no' => $haul->ticket_no,
                'plate' => $haul->vehicle?->plate,
                'hauled_at_label' => $haul->hauled_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
                'site_name' => $haul->site?->name,
                'dump_site_name' => $haul->dumpSite?->name,
                'shift' => $shift,
                'field_session_id' => $haul->field_haul_session_id,
            ])
            ->all();

        return [
            'session' => $this->sessionPayload($session),
            'data' => $hauls,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(FieldHaulSession $session): array
    {
        $shift = $session->shift instanceof Shift ? $session->shift : Shift::from((string) $session->shift);

        return [
            'id' => $session->id,
            'site_id' => $session->site_id,
            'site_name' => $session->site?->name,
            'dump_site_id' => $session->dump_site_id,
            'dump_site_name' => $session->dumpSite?->name,
            'shift' => $shift->value,
            'shift_label' => $shift->label(),
            'locked_at' => $session->locked_at->toIso8601String(),
        ];
    }

    public function assertCanView(User $user, Haul $haul): void
    {
        if ($user->hasRole('yonetici', 'web')) {
            return;
        }

        if ($haul->created_by !== $user->id) {
            abort(404);
        }

        $session = FieldHaulSession::query()
            ->where('user_id', $user->id)
            ->open()
            ->latest('locked_at')
            ->first();

        if (! $session) {
            abort(404);
        }

        $shift = $session->shift instanceof Shift ? $session->shift->value : (string) $session->shift;
        $haulShift = $haul->shift instanceof Shift ? $haul->shift->value : (string) $haul->shift;

        if (
            $haul->site_id !== $session->site_id
            || $haul->dump_site_id !== $session->dump_site_id
            || $haulShift !== $shift
        ) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function showFor(User $user, Haul $haul): array
    {
        $this->assertCanView($user, $haul);
        $haul->loadMissing(['site', 'dumpSite', 'vehicle']);

        return $this->printPayload($haul);
    }

    public function photoPathFor(Haul $haul): string
    {
        $scan = HaulScan::query()
            ->where('haul_id', $haul->id)
            ->where('status', HaulScan::STATUS_ATTACHED)
            ->first();

        if (! $scan) {
            abort(404);
        }

        return $this->images->absolutePath($scan->path);
    }

    public function pruneExpired(): int
    {
        $expired = HaulScan::query()
            ->where('status', HaulScan::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $scan) {
            $this->images->delete($scan->path);
            $scan->delete();
        }

        return $expired->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function printPayload(Haul $haul): array
    {
        $shift = $haul->shift instanceof Shift ? $haul->shift : Shift::from((string) $haul->shift);

        return [
            'id' => $haul->id,
            'title' => 'SEFER HAREKET FİŞİ',
            'ticket_no' => $haul->ticket_no,
            'hauled_at' => $haul->hauled_at->toIso8601String(),
            'hauled_at_label' => $haul->hauled_at->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'plate' => $haul->vehicle?->plate,
            'site_name' => $haul->site?->name,
            'dump_site_name' => $haul->dumpSite?->name,
            'shift' => $shift->value,
            'shift_label' => $shift->label(),
            'tonnage' => (int) $haul->tonnage,
            'qr_payload' => $haul->id,
            'photo_url' => $this->photoUrlFor($haul),
        ];
    }

    public function photoUrlFor(Haul $haul): ?string
    {
        $path = HaulScan::query()
            ->where('haul_id', $haul->id)
            ->where('status', HaulScan::STATUS_ATTACHED)
            ->value('path');

        return is_string($path) && $path !== '' ? $this->images->url($path) : null;
    }

    private function nextTicketNo(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $ticket = now()->format('Ymd-His').'-'.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);

            if (! $this->hauls->ticketTaken($ticket)) {
                return $ticket;
            }
        }

        throw new RuntimeException('Fiş numarası üretilemedi.');
    }
}

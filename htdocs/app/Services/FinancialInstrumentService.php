<?php

namespace App\Services;

use App\Cache\DashboardCache;
use App\Enums\CariEntryKind;
use App\Enums\InstrumentDirection;
use App\Enums\InstrumentMovementType;
use App\Enums\InstrumentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\OperationRejectedException;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentMovement;
use App\Models\FinancialInstrumentPhoto;
use App\Repositories\BankRepository;
use App\Repositories\FinancialInstrumentRepository;
use App\Repositories\PartyRepository;
use App\Services\Api\SecureImageIngest;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * The cheque and note portfolio. A cari entry paid by cheque or note settles the cari at once and
 * puts the paper here; its later steps either move money through an account (collect, pay) or
 * post to the cari again (endorse, return unpaid). Only the last step can be taken back.
 */
class FinancialInstrumentService extends Service
{
    private const PHOTO_FOLDER = 'instrument-photos';

    public function __construct(
        private FinancialInstrumentRepository $instruments,
        private BankRepository $banks,
        private PartyRepository $parties,
        private Ledger $ledger,
        private SecureImageIngest $images,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null): LengthAwarePaginator
    {
        return $this->instruments->search($filters, $sort);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{received: float, received_count: int, given: float, given_count: int, overdue_count: int}
     */
    public function openTotals(array $filters): array
    {
        return $this->instruments->openTotals($filters);
    }

    /**
     * What the action form of this instrument offers: the steps it can take next, the accounts
     * money can go through (the request checks each step's account kind) and the parties to endorse to.
     *
     * @return array{types: list<InstrumentMovementType>, needs_account: list<string>, accounts: array<string, string>, parties: array<string, string>, deposit_bank: ?string}
     */
    public function actionOptions(FinancialInstrument $instrument): array
    {
        $types = $instrument->status->allows();
        $accountTypes = collect($types)->flatMap(fn (InstrumentMovementType $type) => $type->accountTypes())->unique(fn ($type) => $type->value)->values()->all();

        return [
            'types' => $types,
            'needs_account' => collect($types)->filter(fn (InstrumentMovementType $type) => $type->accountTypes() !== [])->map->value->values()->all(),
            'accounts' => $accountTypes === [] ? [] : $this->banks->accountOptionsOf($accountTypes),
            'parties' => in_array(InstrumentMovementType::Endorse, $types, true)
                ? array_diff_key($this->parties->withoutSystem($this->parties->options()), array_flip(array_filter([$instrument->party_id])))
                : [],
            'deposit_bank' => $instrument->status === InstrumentStatus::InCollection
                ? $instrument->movements->last()?->bank_id
                : null,
        ];
    }

    /**
     * The cari entry screen the instrument came from, as [kind, id]; null once that entry is gone.
     *
     * @return array{kind: CariEntryKind, id: string}|null
     */
    public function sourceEntry(FinancialInstrument $instrument): ?array
    {
        $source = $instrument->source;

        if (! $source || $source->trashed()) {
            return null;
        }

        $kind = match ($instrument->source_type) {
            'company_transaction' => CariEntryKind::tryFrom($source->role->value),
            'vehicle_payment' => CariEntryKind::VehiclePayment,
            'party_payment' => CariEntryKind::OwnerPayment,
            'dump_site_payment' => CariEntryKind::DumpPayment,
            'site_transaction' => CariEntryKind::Site,
            default => null,
        };

        return $kind ? ['kind' => $kind, 'id' => $source->getKey()] : null;
    }

    /**
     * Writers lock the row so a step recorded on the cheque screen cannot slip in between the
     * "has it moved on?" check and the change.
     */
    public function forEntry(Model $entry, bool $lock = false): ?FinancialInstrument
    {
        return FinancialInstrument::query()
            ->where('source_type', $entry->getMorphClass())
            ->where('source_id', $entry->getKey())
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /**
     * Keeps the entry's cheque or note in step with what the entry says now. The paper's amount,
     * side, kind and party cannot change once it has moved on. Callers must be inside a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncEntry(CariEntryKind $kind, Model $entry, array $data, ?string $partyId, ?string $userId): void
    {
        $instrument = $this->forEntry($entry, lock: true);
        $method = PaymentMethod::tryFrom((string) ($data['payment_method'] ?? ''));

        if (! $kind->tracksInstruments() || ! $method?->hasDueDate()) {
            if ($instrument) {
                $this->assertUntouched($instrument);
                $instrument->delete();
                DashboardCache::forgetFinanceReminders();
            }

            return;
        }

        $issuedOn = Carbon::parse($data['occurred_on'])->toDateString();
        $direction = InstrumentDirection::fromLedgerSide($kind->directionMode() === 'choice' ? (string) $data['direction'] : $kind->directionMode());
        $attributes = [
            'payment_method' => $method,
            'direction' => $direction,
            'amount' => LedgerLine::money($data['amount']),
            'issued_on' => $issuedOn,
            'due_on' => $data['due_on'] ?? $issuedOn,
            'number' => $data['cheque_no'] ?? null,
            'bank_name' => $data['instrument_bank'] ?? null,
            'drawer' => $data['instrument_drawer'] ?? null,
            'party_id' => $partyId,
            'description' => $data['description'] ?? null,
        ];

        if (! $instrument) {
            $instrument = FinancialInstrument::query()->create([
                ...$attributes,
                'status' => $direction->initialStatus(),
                'source_type' => $entry->getMorphClass(),
                'source_id' => $entry->getKey(),
                'created_by' => $userId,
            ]);
        } else {
            $instrument->fill($attributes);

            if ($instrument->isDirty(['payment_method', 'direction', 'amount', 'party_id'])) {
                $this->assertUntouched($instrument);
                $instrument->status = $direction->initialStatus();
            }

            $firstStep = $instrument->movements()->first();

            if ($firstStep && $firstStep->occurred_on->lt(Carbon::parse($issuedOn))) {
                throw new OperationRejectedException(sprintf(
                    'İşlem tarihi, %s kaydının ilk adımından (%s) sonra olamaz.',
                    mb_strtolower($instrument->payment_method->label(), 'UTF-8'),
                    $firstStep->occurred_on->format('d.m.Y'),
                ));
            }

            $instrument->save();
        }

        DashboardCache::forgetFinanceReminders();
        $this->storePhotos($instrument, $data['photos'] ?? [], $userId);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function addPhotos(FinancialInstrument $instrument, array $files, ?string $userId): void
    {
        $this->transaction(fn () => $this->storePhotos($instrument, $files, $userId));
    }

    public function deletePhoto(FinancialInstrumentPhoto $photo): void
    {
        $photo->delete();
    }

    /**
     * @return array{path: string, mime: string}
     */
    public function photoFile(FinancialInstrumentPhoto $photo): array
    {
        return ['path' => $this->images->absolutePath($photo->path), 'mime' => $this->images->mimeForPath($photo->path)];
    }

    /**
     * The entry's posting moved to another party (a temporary plate handed to its real owner): the
     * cheque follows, and so does whatever its return unpaid already posted. Callers must be inside a transaction.
     */
    public function followEntry(Model $entry, string $partyId): void
    {
        $instrument = $this->forEntry($entry, lock: true);

        if (! $instrument || $instrument->party_id === $partyId) {
            return;
        }

        $instrument->update(['party_id' => $partyId]);
        DashboardCache::forgetFinanceReminders();

        foreach ($instrument->movements()->where('type', InstrumentMovementType::Bounce)->get() as $movement) {
            $this->ledger->sync($movement, $this->lines($instrument, $movement));
        }
    }

    /**
     * The entry is going away; its cheque or note goes with it unless it has already moved on.
     * Callers must be inside a transaction.
     */
    public function releaseEntry(Model $entry): void
    {
        if ($instrument = $this->forEntry($entry, lock: true)) {
            $this->assertUntouched($instrument);
            $instrument->delete();
            DashboardCache::forgetFinanceReminders();
        }
    }

    /**
     * @param  array{type: string, occurred_on: string, bank_id?: ?string, party_id?: ?string, description?: ?string}  $data
     */
    public function record(FinancialInstrument $instrument, array $data, ?string $userId): FinancialInstrumentMovement
    {
        return $this->transaction(function () use ($instrument, $data, $userId): FinancialInstrumentMovement {
            /** @var FinancialInstrument $instrument */
            $instrument = FinancialInstrument::query()->lockForUpdate()->findOrFail($instrument->getKey());
            $type = InstrumentMovementType::from($data['type']);
            $last = $instrument->movements()->get()->last();
            $on = Carbon::parse($data['occurred_on']);

            if (! in_array($type, $instrument->status->allows(), true)) {
                throw new OperationRejectedException(sprintf(
                    '%s durumundaki bir %s için "%s" yapılamaz.',
                    $instrument->status->label(),
                    mb_strtolower($instrument->payment_method->label(), 'UTF-8'),
                    $type->action(),
                ));
            }

            if ($on->lt($last?->occurred_on ?? $instrument->issued_on)) {
                throw new OperationRejectedException(sprintf(
                    'İşlem tarihi %s tarihinden önce olamaz.',
                    ($last?->occurred_on ?? $instrument->issued_on)->format('d.m.Y'),
                ));
            }

            if ($type === InstrumentMovementType::Bounce && $instrument->party_id === null) {
                throw new OperationRejectedException('Bu kaydın carisi yok; karşılıksız çıktığında borçlandırılacak kimse bulunamadı.');
            }

            /** @var FinancialInstrumentMovement $movement */
            $movement = $instrument->movements()->create([
                'type' => $type,
                'occurred_on' => $on->toDateString(),
                'bank_id' => $type->accountTypes() !== [] ? $data['bank_id'] : null,
                'party_id' => match (true) {
                    $type === InstrumentMovementType::Endorse => $data['party_id'],
                    $type === InstrumentMovementType::Bounce && $last?->type === InstrumentMovementType::Endorse => $last->party_id,
                    default => null,
                },
                'description' => $data['description'] ?? null,
                'created_by' => $userId,
            ]);

            $this->ledger->sync($movement, $this->lines($instrument, $movement));
            $instrument->update(['status' => $type->resultingStatus()]);
            DashboardCache::forgetFinanceReminders();

            return $movement;
        });
    }

    /**
     * Takes back the last step: its cari postings are reversed and its account movement disappears.
     */
    public function undoLast(FinancialInstrument $instrument): void
    {
        $this->transaction(function () use ($instrument): void {
            /** @var FinancialInstrument $instrument */
            $instrument = FinancialInstrument::query()->lockForUpdate()->findOrFail($instrument->getKey());
            $movements = $instrument->movements()->get();
            $last = $movements->pop() ?? throw new OperationRejectedException('Geri alınacak işlem yok.');

            $this->ledger->void($last);
            $last->delete();

            $instrument->update([
                'status' => $movements->last()?->type->resultingStatus() ?? $instrument->direction->initialStatus(),
            ]);
            DashboardCache::forgetFinanceReminders();
        });
    }

    /**
     * @return list<LedgerLine>
     */
    private function lines(FinancialInstrument $instrument, FinancialInstrumentMovement $movement): array
    {
        $paper = trim($instrument->payment_method->label().($instrument->number ? ' No '.$instrument->number : ''));
        $text = fn (string $what): string => trim($paper.' · '.$what.($movement->description ? ' · '.$movement->description : ''));

        return match ($movement->type) {
            InstrumentMovementType::Endorse => [
                LedgerLine::debit($movement->party_id, $movement->occurred_on, $instrument->amount, $text('ciro'))
                    ->paidBy($instrument->payment_method),
            ],
            InstrumentMovementType::Bounce => $instrument->direction === InstrumentDirection::Received
                ? array_values(array_filter([
                    LedgerLine::debit($instrument->party_id, $movement->occurred_on, $instrument->amount, $text('karşılıksız')),
                    $movement->party_id
                        ? LedgerLine::credit($movement->party_id, $movement->occurred_on, $instrument->amount, $text('ciro iadesi'))
                        : null,
                ]))
                : [LedgerLine::credit($instrument->party_id, $movement->occurred_on, $instrument->amount, $text('karşılıksız'))],
            default => [],
        };
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function storePhotos(FinancialInstrument $instrument, array $files, ?string $userId): void
    {
        foreach (array_values($files) as $index => $file) {
            $instrument->photos()->create([
                'path' => $this->images->storeDocument($file, self::PHOTO_FOLDER, sprintf('%s-%s-%d', $instrument->getKey(), now()->format('YmdHisv'), $index)),
                'created_by' => $userId,
            ]);
        }
    }

    private function assertUntouched(FinancialInstrument $instrument): void
    {
        if ($instrument->movements()->exists()) {
            throw new OperationRejectedException(sprintf(
                'Bu hareketin %s kaydı "%s" durumunda; önce Çek / Senet ekranından işlemlerini geri alın.',
                mb_strtolower($instrument->payment_method->label(), 'UTF-8'),
                $instrument->status->label(),
            ));
        }
    }
}

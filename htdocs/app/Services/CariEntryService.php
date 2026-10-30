<?php

namespace App\Services;

use App\Enums\CariEntryKind;
use App\Enums\PaymentMethod;
use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Models\AccountTransfer;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\DumpSite;
use App\Models\DumpSitePayment;
use App\Models\FinancialInstrument;
use App\Models\FuelStation;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Models\PartyTransfer;
use App\Models\Site;
use App\Models\SiteBill;
use App\Models\SiteTransaction;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use App\Repositories\BankRepository;
use App\Repositories\CariEntryRepository;
use App\Repositories\CashCategoryRepository;
use App\Repositories\DumpSiteRepository;
use App\Repositories\FuelStationRepository;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CariEntryService extends Service
{
    public function __construct(
        private CariEntryRepository $entries,
        private PartyRepository $parties,
        private SiteRepository $sites,
        private DumpSiteRepository $dumpSites,
        private FuelStationRepository $stations,
        private BankRepository $banks,
        private CashCategoryRepository $categories,
        private Ledger $ledger,
        private VehicleOwnershipService $ownerships,
        private FinancialInstrumentService $instruments,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->entries->search($filters, $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(?array $current = null): array
    {
        $parties = [];

        foreach (CariEntryKind::cases() as $kind) {
            if ($role = $kind->partyRole()) {
                $parties[$kind->value] = $this->parties->withoutSystem($this->parties->options($role, $current['party_id'] ?? null));
            } elseif ($kind->takesAnyParty()) {
                $parties[$kind->value] = $this->parties->withoutSystem($this->parties->options(include: array_filter([$current['party_id'] ?? null, $current['counter_party_id'] ?? null])));
            }
        }

        return [
            'kinds' => CariEntryKind::options(),
            'directions' => collect(CariEntryKind::cases())->mapWithKeys(fn (CariEntryKind $kind) => [$kind->value => $kind->directionLabels()])->all(),
            'parties' => $parties,
            'sites' => $this->sites->options(activeOnly: true, include: $current['site_id'] ?? null),
            'stations' => $this->stations->options(activeOnly: true, include: $current['fuel_station_id'] ?? null),
            'dumpSites' => $this->dumpSites->options(activeOnly: true, include: $current['dump_site_id'] ?? null),
            'banks' => $this->banks->accountOptions($current['bank_id'] ?? null),
            'categories' => $this->categories->options(include: $current['cash_category_id'] ?? null),
            'methods' => PaymentMethod::options(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId): void
    {
        $this->transaction(function () use ($data, $userId): void {
            $this->assertPlateOpen($this->kind($data), null, $data);
            $entry = $this->persist($this->kind($data), $data, $userId);
            $this->post($entry, $data);
            $this->instruments->syncEntry($this->kind($data), $entry, $data, $this->postedParty($entry), $userId);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CariEntryKind $kind, string $id, array $data): void
    {
        $this->transaction(function () use ($kind, $id, $data): void {
            $entry = $this->find($kind, $id);
            $this->assertPlateOpen($kind, $entry, $data);
            $postedParty = $kind === CariEntryKind::DumpPayment && $entry->dump_site_id === $data['dump_site_id']
                ? $this->ledger->live($entry)->first()?->party_id
                : null;

            $entry = $this->persist($kind, $data, $entry->created_by, $entry);
            $this->post($entry, $data, $postedParty);
            $this->instruments->syncEntry($kind, $entry, $data, $this->postedParty($entry), $entry->created_by);
        });
    }

    public function delete(CariEntryKind $kind, string $id): void
    {
        $this->transaction(function () use ($kind, $id): void {
            $entry = $this->find($kind, $id);
            $this->assertPlateOpen($kind, $entry, null);
            $this->instruments->releaseEntry($entry);

            if ($kind->postsLedger()) {
                $this->ledger->void($entry);
            }

            $entry->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function form(CariEntryKind $kind, string $id): array
    {
        $entry = $this->find($kind, $id);
        $debit = (float) ($entry->debit ?? ($kind->directionMode() === 'debit' ? $entry->amount : 0));
        $credit = (float) ($entry->credit ?? ($kind->directionMode() === 'credit' ? $entry->amount : 0));

        if ($kind === CariEntryKind::VehiclePayment) {
            $debit = (float) $entry->amount;
            $credit = 0;
        }

        if ($kind === CariEntryKind::VehicleReceivable) {
            $debit = 0;
            $credit = (float) $entry->amount;
        }

        $instrument = $this->instruments->forEntry($entry);

        return [
            'kind' => $kind->value,
            'party_id' => $entry->party_id ?? $entry->debit_party_id ?? $entry->dumpSite?->party_id,
            'counter_party_id' => $entry->credit_party_id ?? null,
            'direction' => $credit > 0 ? 'credit' : 'debit',
            'amount' => number_format($debit > 0 ? $debit : $credit, 2, '.', ''),
            'occurred_on' => $entry->occurred_on,
            'payment_method' => $entry->payment_method ?? null,
            'cash_category_id' => $entry->cash_category_id ?? null,
            'description' => $entry->description,
            'document_no' => $entry->document_no ?? null,
            'site_id' => $entry->site_id ?? null,
            'fuel_station_id' => $entry->fuel_station_id ?? null,
            'liters' => $entry->liters ?? null,
            'unit_price' => $entry->unit_price ?? null,
            'plate' => method_exists($entry, 'vehicle') ? $entry->vehicle?->plate : null,
            'dump_site_id' => $entry->dump_site_id ?? null,
            'bank_id' => $entry->bank_id ?? $entry->from_bank_id ?? null,
            'counter_bank_id' => $entry->to_bank_id ?? null,
            'cheque_no' => $entry->cheque_no ?? $instrument?->number,
            'due_on' => $instrument?->due_on->toDateString(),
            'instrument_bank' => $instrument?->bank_name,
            'instrument_drawer' => $instrument?->drawer,
            'instrument' => $instrument,
            'receiver_name' => $entry->receiver_name ?? null,
            'receiver_national_id' => $entry->receiver_national_id ?? null,
        ];
    }

    /**
     * Read-only labels for the detail screen; links holds only the fields this kind uses.
     *
     * @return array{direction: string, amount: string, occurred_on: Carbon, payment_method: ?PaymentMethod, category: ?string, description: ?string, links: array<string, string>, instrument: ?FinancialInstrument, corrections: ?Collection<int, array<string, mixed>>}
     */
    public function details(CariEntryKind $kind, string $id): array
    {
        $form = $this->form($kind, $id);
        $name = fn (string $model, ?string $key): ?string => $key ? $model::withTrashed()->find($key)?->name : null;
        $method = $form['payment_method'];

        $links = array_filter([
            ($kind === CariEntryKind::PartyTransfer ? 'Borçlanan cari' : 'Cari') => $name(Party::class, $form['party_id']),
            'Alacaklanan cari' => $name(Party::class, $form['counter_party_id']),
            'Plaka' => $form['plate'],
            'Döküm yeri' => $name(DumpSite::class, $form['dump_site_id']),
            ($form['instrument'] ? $form['instrument']->payment_method->label().' no' : 'Çek no') => $form['cheque_no'],
            'Şantiye' => $name(Site::class, $form['site_id']),
            'Yakıt yeri' => $name(FuelStation::class, $form['fuel_station_id']),
            'Litre' => $form['liters'] !== null ? number_format((float) $form['liters'], 2, ',', '.') : null,
            'Birim fiyat' => $form['unit_price'] !== null ? number_format((float) $form['unit_price'], 4, ',', '.').' TL' : null,
            'Belge no' => $form['document_no'],
            'Teslim alan' => $form['receiver_name'] ? trim($form['receiver_name'].' '.($form['receiver_national_id'] ?? '')) : null,
            ($kind === CariEntryKind::AccountTransfer ? 'Çıkan hesap' : 'Hesap') => $name(Bank::class, $form['bank_id']),
            'Giren hesap' => $name(Bank::class, $form['counter_bank_id']),
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'direction' => match (true) {
                $kind === CariEntryKind::PartyTransfer => 'Borçlanan cariye borç, alacaklanan cariye alacak',
                $kind === CariEntryKind::AccountTransfer => 'Çıkan hesaptan düşer, giren hesaba eklenir',
                default => $kind->directionLabels()[$form['direction']],
            },
            'amount' => $form['amount'],
            'occurred_on' => Carbon::parse($form['occurred_on']),
            'payment_method' => $method instanceof PaymentMethod ? $method : PaymentMethod::tryFrom((string) $method),
            'category' => $name(CashCategory::class, $form['cash_category_id']),
            'description' => $form['description'],
            'links' => $links,
            'instrument' => $form['instrument'],
            'corrections' => $kind->postsLedger() ? $this->entries->corrections($this->find($kind, $id)) : null,
        ];
    }

    public function find(CariEntryKind $kind, string $id): Model
    {
        $query = match ($kind) {
            CariEntryKind::Supplier, CariEntryKind::Customer, CariEntryKind::Fuel, CariEntryKind::Market => CompanyTransaction::query()->where('role', $kind->value),
            CariEntryKind::VehiclePayment => VehiclePayment::query(),
            CariEntryKind::VehicleReceivable => VehicleReceivable::query(),
            CariEntryKind::OwnerPayment => PartyPayment::query(),
            CariEntryKind::DumpPayment => DumpSitePayment::query(),
            CariEntryKind::Site => SiteTransaction::query(),
            CariEntryKind::Cash => CashEntry::query(),
            CariEntryKind::Opening => OpeningBalance::query(),
            CariEntryKind::PartyTransfer => PartyTransfer::query(),
            CariEntryKind::SiteBill => SiteBill::query(),
            CariEntryKind::AccountTransfer => AccountTransfer::query(),
        };

        return $query->find($id) ?? throw new OperationException('Kayıt bulunamadı.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(CariEntryKind $kind, array $data, ?string $userId, ?Model $existing = null): Model
    {
        $direction = $kind->directionMode() === 'choice' ? $data['direction'] : $kind->directionMode();
        $amount = LedgerLine::money($data['amount']);
        $date = Carbon::parse($data['occurred_on'])->toDateString();
        $method = $data['payment_method'] ?? null;
        $shared = [
            'occurred_on' => $date,
            'payment_method' => $method,
            'bank_id' => $data['bank_id'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => $existing?->created_by ?? $userId,
        ];

        $attributes = match ($kind) {
            CariEntryKind::Supplier, CariEntryKind::Customer, CariEntryKind::Fuel, CariEntryKind::Market => [
                ...$shared,
                'party_id' => $data['party_id'],
                'role' => $kind->value,
                'site_id' => $data['site_id'] ?? null,
                'debit' => $direction === 'debit' ? $amount : 0,
                'credit' => $direction === 'credit' ? $amount : 0,
                'cash_category_id' => $data['cash_category_id'] ?? null,
                'document_no' => $data['document_no'] ?? null,
                'fuel_station_id' => $kind === CariEntryKind::Market ? null : ($data['fuel_station_id'] ?? null),
                'liters' => $kind === CariEntryKind::Fuel ? ($data['liters'] ?? null) : null,
                'unit_price' => $kind === CariEntryKind::Fuel ? ($data['unit_price'] ?? null) : null,
                'vehicle_id' => $kind === CariEntryKind::Market ? $this->vehicle($data)?->id : null,
                'owner_party_id' => $kind === CariEntryKind::Market ? $this->ownerFor($data, $existing, 'owner_party_id') : null,
            ],
            CariEntryKind::VehiclePayment => [
                ...$shared,
                'vehicle_id' => $this->vehicle($data)->id,
                'party_id' => $this->ownerFor($data, $existing),
                'amount' => $amount,
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_national_id' => $data['receiver_national_id'] ?? null,
            ],
            CariEntryKind::VehicleReceivable => [
                ...$shared,
                'vehicle_id' => $this->vehicle($data)->id,
                'party_id' => $this->ownerFor($data, $existing),
                'amount' => $amount,
            ],
            CariEntryKind::OwnerPayment => [
                ...$shared,
                'party_id' => $data['party_id'],
                'cash_category_id' => $data['cash_category_id'] ?? null,
                'amount' => $amount,
            ],
            CariEntryKind::DumpPayment => [
                ...$shared,
                'dump_site_id' => $data['dump_site_id'],
                'amount' => $amount,
                'cheque_no' => $data['cheque_no'] ?? null,
            ],
            CariEntryKind::Site => [
                ...$shared,
                'site_id' => $data['site_id'],
                'party_id' => $direction === 'credit' ? $this->siteParty($data, $existing) : null,
                'debit' => $direction === 'debit' ? $amount : 0,
                'credit' => $direction === 'credit' ? $amount : 0,
            ],
            CariEntryKind::SiteBill => [
                'site_id' => $data['site_id'],
                'party_id' => $this->siteParty($data, $existing),
                'amount' => $amount,
                'occurred_on' => $date,
                'description' => $data['description'] ?? null,
                'created_by' => $existing?->created_by ?? $userId,
            ],
            CariEntryKind::Cash => [
                'cash_category_id' => $data['cash_category_id'] ?? null,
                'bank_id' => $data['bank_id'] ?? null,
                'debit' => $direction === 'debit' ? $amount : 0,
                'credit' => $direction === 'credit' ? $amount : 0,
                'occurred_on' => $date,
                'description' => $data['description'] ?? null,
                'created_by' => $existing?->created_by ?? $userId,
            ],
            CariEntryKind::Opening => [
                'party_id' => $data['party_id'],
                'debit' => $direction === 'debit' ? $amount : 0,
                'credit' => $direction === 'credit' ? $amount : 0,
                'occurred_on' => $date,
                'description' => $data['description'] ?? null,
                'created_by' => $existing?->created_by ?? $userId,
            ],
            CariEntryKind::PartyTransfer => [
                'debit_party_id' => $data['party_id'],
                'credit_party_id' => $data['counter_party_id'],
                'amount' => $amount,
                'occurred_on' => $date,
                'description' => $data['description'] ?? null,
                'created_by' => $existing?->created_by ?? $userId,
            ],
            CariEntryKind::AccountTransfer => [
                'from_bank_id' => $data['bank_id'],
                'to_bank_id' => $data['counter_bank_id'],
                'amount' => $amount,
                'occurred_on' => $date,
                'description' => $data['description'] ?? null,
                'created_by' => $existing?->created_by ?? $userId,
            ],
        };

        if ($existing) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        $class = match ($kind) {
            CariEntryKind::Supplier, CariEntryKind::Customer, CariEntryKind::Fuel, CariEntryKind::Market => CompanyTransaction::class,
            CariEntryKind::VehiclePayment => VehiclePayment::class,
            CariEntryKind::VehicleReceivable => VehicleReceivable::class,
            CariEntryKind::OwnerPayment => PartyPayment::class,
            CariEntryKind::DumpPayment => DumpSitePayment::class,
            CariEntryKind::Site => SiteTransaction::class,
            CariEntryKind::Cash => CashEntry::class,
            CariEntryKind::Opening => OpeningBalance::class,
            CariEntryKind::PartyTransfer => PartyTransfer::class,
            CariEntryKind::SiteBill => SiteBill::class,
            CariEntryKind::AccountTransfer => AccountTransfer::class,
        };

        return $class::query()->create($attributes);
    }

    /**
     * A dump payment stays on the operator it was paid to, even if the dump site has changed operator since.
     *
     * @param  array<string, mixed>  $data
     */
    private function post(Model $entry, array $data, ?string $postedParty = null): void
    {
        $kind = $this->kind($data);

        if (! $kind->postsLedger()) {
            return;
        }

        if ($entry instanceof PartyTransfer) {
            $this->ledger->sync($entry, [
                LedgerLine::debit($entry->debit_party_id, $entry->occurred_on, $entry->amount, $this->text($kind, $data)),
                LedgerLine::credit($entry->credit_party_id, $entry->occurred_on, $entry->amount, $this->text($kind, $data)),
            ]);

            return;
        }

        if ($kind === CariEntryKind::Site && $entry->party_id === null) {
            $this->ledger->sync($entry, []);

            return;
        }

        $direction = $kind->directionMode() === 'choice' ? $data['direction'] : $kind->directionMode();
        $partyId = $postedParty ?? match ($kind) {
            CariEntryKind::DumpPayment => DumpSite::query()->find($entry->dump_site_id)?->party_id
                ?? throw new OperationRejectedException('Döküm yerinin işletmecisi yok.'),
            CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable => $entry->party_id,
            default => $entry->party_id,
        };
        $line = $direction === 'debit'
            ? LedgerLine::debit($partyId, $entry->occurred_on, $data['amount'], $this->text($kind, $data), $entry->vehicle_id ?? null)
            : LedgerLine::credit($partyId, $entry->occurred_on, $data['amount'], $this->text($kind, $data), $entry->vehicle_id ?? null);

        $method = isset($data['payment_method']) ? PaymentMethod::from($data['payment_method']) : null;

        $this->ledger->sync($entry, [$line->paidBy($method, $data['cash_category_id'] ?? null, $entry->bank_id ?? null)]);
    }

    /**
     * A site entry stays on the cari it was posted to, even if the site has changed cari since;
     * only moving the entry to another site picks that site's current cari.
     *
     * @param  array<string, mixed>  $data
     */
    private function siteParty(array $data, ?Model $existing): ?string
    {
        $wasPosted = $existing instanceof SiteBill || (float) ($existing->credit ?? 0) > 0;

        if ($existing && $existing->site_id === $data['site_id'] && $wasPosted) {
            return $existing->party_id;
        }

        return Site::query()->find($data['site_id'])?->party_id;
    }

    /**
     * The party the entry settled on the cari: the one a cheque or note came from or went to.
     */
    private function postedParty(Model $entry): ?string
    {
        return $this->ledger->live($entry)->first()?->party_id;
    }

    /**
     * Plate payments and receivables cannot touch a period a transfer has closed, before or after the edit.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function assertPlateOpen(CariEntryKind $kind, ?Model $entry, ?array $data): void
    {
        if (! in_array($kind, [CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable], true)) {
            return;
        }

        if ($entry) {
            $this->ownerships->assertOpen($entry->vehicle, $entry->occurred_on, $entry->party_id);
        }

        if ($data && ($vehicle = $this->vehicle($data))) {
            $this->ownerships->assertOpen($vehicle, Carbon::parse($data['occurred_on']));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function text(CariEntryKind $kind, array $data): string
    {
        return trim($kind->label().(empty($data['description']) ? '' : ' · '.$data['description']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function kind(array $data): CariEntryKind
    {
        return CariEntryKind::from($data['kind']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function vehicle(array $data): ?Vehicle
    {
        if (empty($data['plate'])) {
            return null;
        }

        return Vehicle::findByPlate((string) $data['plate'])
            ?? throw new OperationException('Plaka bulunamadı.');
    }

    /**
     * An edit that keeps the plate and date keeps the party the money went to, even after a
     * back-dated transfer moved that date to another owner.
     *
     * @param  array<string, mixed>  $data
     */
    private function ownerFor(array $data, ?Model $existing, string $column = 'party_id'): ?string
    {
        $vehicle = $this->vehicle($data);

        if ($existing && $vehicle && $existing->vehicle_id === $vehicle->id && $existing->{$column} !== null
            && Carbon::parse($existing->occurred_on)->isSameDay(Carbon::parse($data['occurred_on']))) {
            return $existing->{$column};
        }

        return $this->ownerId($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ownerId(array $data): ?string
    {
        $vehicle = $this->vehicle($data);

        if (! $vehicle) {
            return null;
        }

        return $vehicle->ownershipOn(Carbon::parse($data['occurred_on']))?->party_id
            ?? throw new OperationRejectedException(sprintf(
                '%s aracının %s tarihinde kayıtlı sahibi yok.',
                $vehicle->plate,
                Carbon::parse($data['occurred_on'])->format('d.m.Y'),
            ));
    }
}

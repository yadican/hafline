<?php

namespace App\Services;

use App\Enums\CariEntryKind;
use App\Enums\PaymentMethod;
use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\DumpSite;
use App\Models\DumpSitePayment;
use App\Models\FuelStation;
use App\Models\Party;
use App\Models\PartyPayment;
use App\Models\Site;
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
                $parties[$kind->value] = $this->parties->options($role, $current['party_id'] ?? null);
            }
        }

        return [
            'kinds' => CariEntryKind::options(),
            'parties' => $parties,
            'sites' => $this->sites->options(activeOnly: true, include: $current['site_id'] ?? null),
            'stations' => $this->stations->options(activeOnly: true, include: $current['fuel_station_id'] ?? null),
            'dumpSites' => $this->dumpSites->options(activeOnly: true, include: $current['dump_site_id'] ?? null),
            'banks' => $this->banks->options(activeOnly: true, include: $current['bank_id'] ?? null),
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
            $entry = $this->persist($this->kind($data), $data, $userId);
            $this->post($entry, $data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CariEntryKind $kind, string $id, array $data): void
    {
        $this->transaction(function () use ($kind, $id, $data): void {
            $entry = $this->find($kind, $id);
            $entry = $this->persist($kind, $data, $entry->created_by, $entry);
            $this->post($entry, $data);
        });
    }

    public function delete(CariEntryKind $kind, string $id): void
    {
        $this->transaction(function () use ($kind, $id): void {
            $entry = $this->find($kind, $id);

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

        return [
            'kind' => $kind->value,
            'party_id' => $entry->party_id ?? $entry->dumpSite?->party_id,
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
            'bank_id' => $entry->bank_id ?? null,
            'cheque_no' => $entry->cheque_no ?? null,
        ];
    }

    /**
     * Read-only labels for the detail screen; links holds only the fields this kind uses.
     *
     * @return array{direction: string, amount: string, occurred_on: Carbon, payment_method: ?PaymentMethod, category: ?string, description: ?string, links: array<string, string>}
     */
    public function details(CariEntryKind $kind, string $id): array
    {
        $form = $this->form($kind, $id);
        $name = fn (string $model, ?string $key): ?string => $key ? $model::withTrashed()->find($key)?->name : null;
        $method = $form['payment_method'];

        $links = array_filter([
            'Cari' => $name(Party::class, $form['party_id']),
            'Plaka' => $form['plate'],
            'Döküm yeri' => $name(DumpSite::class, $form['dump_site_id']),
            'Çek no' => $form['cheque_no'],
            'Şantiye' => $name(Site::class, $form['site_id']),
            'Yakıt yeri' => $name(FuelStation::class, $form['fuel_station_id']),
            'Litre' => $form['liters'] !== null ? number_format((float) $form['liters'], 2, ',', '.') : null,
            'Birim fiyat' => $form['unit_price'] !== null ? number_format((float) $form['unit_price'], 4, ',', '.').' TL' : null,
            'Belge no' => $form['document_no'],
            'Banka' => $name(Bank::class, $form['bank_id']),
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'direction' => $form['direction'] === 'credit' ? 'Alacak' : 'Borç',
            'amount' => $form['amount'],
            'occurred_on' => Carbon::parse($form['occurred_on']),
            'payment_method' => $method instanceof PaymentMethod ? $method : PaymentMethod::tryFrom((string) $method),
            'category' => $name(CashCategory::class, $form['cash_category_id']),
            'description' => $form['description'],
            'links' => $links,
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
                'owner_party_id' => $kind === CariEntryKind::Market ? $this->ownerId($data) : null,
            ],
            CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable => [
                ...$shared,
                'vehicle_id' => $this->vehicle($data)->id,
                'party_id' => $this->ownerId($data),
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
                'debit' => $direction === 'debit' ? $amount : 0,
                'credit' => $direction === 'credit' ? $amount : 0,
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
        };

        return $class::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function post(Model $entry, array $data): void
    {
        $kind = $this->kind($data);

        if (! $kind->postsLedger()) {
            return;
        }

        $direction = $kind->directionMode() === 'choice' ? $data['direction'] : $kind->directionMode();
        $partyId = match ($kind) {
            CariEntryKind::DumpPayment => DumpSite::query()->find($entry->dump_site_id)?->party_id
                ?? throw new OperationRejectedException('Döküm yerinin işletmecisi yok.'),
            CariEntryKind::VehiclePayment, CariEntryKind::VehicleReceivable => $entry->party_id,
            default => $entry->party_id,
        };
        $line = $direction === 'debit'
            ? LedgerLine::debit($partyId, $entry->occurred_on, $data['amount'], $this->text($kind, $data), $entry->vehicle_id ?? null)
            : LedgerLine::credit($partyId, $entry->occurred_on, $data['amount'], $this->text($kind, $data), $entry->vehicle_id ?? null);

        $method = isset($data['payment_method']) ? PaymentMethod::from($data['payment_method']) : null;

        $this->ledger->sync($entry, [$line->paidBy($method, $data['cash_category_id'] ?? null)]);
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

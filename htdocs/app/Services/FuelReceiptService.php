<?php

namespace App\Services;

use App\Enums\Shift;
use App\Exceptions\OperationException;
use App\Exceptions\OperationRejectedException;
use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\Vehicle;
use App\Repositories\FuelReceiptRepository;
use App\Repositories\FuelStationRepository;
use App\Repositories\HaulLookupRepository;
use App\Repositories\SiteRepository;
use App\Services\Ledger\FuelReceiptLedger;
use App\Support\Sort;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class FuelReceiptService extends Service
{
    public function __construct(
        private FuelReceiptRepository $receipts,
        private SiteRepository $sites,
        private FuelStationRepository $stations,
        private HaulLookupRepository $lookups,
        private FuelReceiptLedger $ledger,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->receipts->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{quantity: float, amount: float}
     */
    public function totals(array $filters): array
    {
        return $this->receipts->totals($filters);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function searchOptions(): array
    {
        return [
            'sites' => $this->lookups->sites(),
            'stations' => $this->stations->options(activeOnly: false),
            'owners' => $this->lookups->owners(),
            'creators' => $this->lookups->creators(),
            'shifts' => Shift::options(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function formOptions(?FuelReceipt $receipt = null): array
    {
        return [
            'stations' => $this->receipts->stationOptions($receipt?->fuel_station_id),
            'sites' => $this->sites->options(activeOnly: true, include: $receipt?->site_id),
            'shifts' => Shift::options(),
        ];
    }

    public function ticketTaken(string $ticketNo): bool
    {
        return $this->receipts->ticketTaken($ticketNo);
    }

    public function ownerOn(Vehicle $vehicle, CarbonInterface $date): ?string
    {
        return $vehicle->ownershipOn($date)?->party_id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId): FuelReceipt
    {
        $vehicle = $this->resolve($data);

        return $this->transaction(function () use ($data, $userId, $vehicle): FuelReceipt {
            /** @var FuelReceipt $receipt */
            $receipt = $this->receipts->create([...$this->attributes($data, $vehicle), 'created_by' => $userId]);
            $this->ledger->sync($receipt);

            return $receipt;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FuelReceipt $receipt, array $data): FuelReceipt
    {
        $vehicle = $this->resolve($data);

        return $this->transaction(function () use ($receipt, $data, $vehicle): FuelReceipt {
            /** @var FuelReceipt $updated */
            $updated = $this->receipts->update($receipt, $this->attributes($data, $vehicle));
            $this->ledger->sync($updated);

            return $updated;
        });
    }

    public function delete(FuelReceipt $receipt): void
    {
        $this->transaction(function () use ($receipt): void {
            $this->ledger->void($receipt);
            $this->receipts->delete($receipt);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolve(array $data): Vehicle
    {
        return Vehicle::findByPlate((string) $data['plate'])
            ?? throw new OperationException('Plaka bulunamadı.');
    }

    /**
     * A blank unit price falls back to the station's current price.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Vehicle $vehicle): array
    {
        $issuedOn = Carbon::parse($data['issued_on'])->startOfDay();
        $party = $this->ownerOn($vehicle, $issuedOn);
        $station = FuelStation::query()->find($data['fuel_station_id']);
        $price = $data['unit_price'] ?? $station?->unit_price;

        if (! $party) {
            throw new OperationRejectedException(sprintf('%s aracının %s tarihinde kayıtlı sahibi yok.', $vehicle->plate, $issuedOn->format('d.m.Y')));
        }

        if (! $station) {
            throw new OperationRejectedException('Yakıt yeri bulunamadı.');
        }

        if ($price === null || (float) $price <= 0) {
            throw new OperationRejectedException(sprintf('%s için birim fiyat tanımlı değil.', $station->name));
        }

        return [
            'ticket_no' => $data['ticket_no'] ?? null,
            'vehicle_id' => $vehicle->id,
            'party_id' => $party,
            'fuel_station_id' => $station->id,
            'fuel_party_id' => $station->party_id,
            'site_id' => $data['site_id'] ?? null,
            'quantity' => $data['quantity'],
            'unit_price' => $price,
            'issued_on' => $issuedOn->toDateString(),
            'km' => $data['km'] ?? null,
            'received_by' => $data['received_by'] ?? null,
            'shift' => $data['shift'] ?? null,
            'haul_ticket_count' => $data['haul_ticket_count'] ?? null,
        ];
    }
}

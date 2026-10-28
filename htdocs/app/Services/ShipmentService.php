<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Enums\Withholding;
use App\Exceptions\OperationRejectedException;
use App\Models\DeliveryAddress;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Repositories\PartyRepository;
use App\Repositories\ShipmentRepository;
use App\Services\Ledger\LedgerLine;
use App\Services\Ledger\ShipmentLedger;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ShipmentService extends Service
{
    public function __construct(
        private ShipmentRepository $shipments,
        private PartyRepository $parties,
        private ShipmentLedger $ledger,
        private VehicleOwnershipService $ownerships,
    ) {}

    /**
     * @param  array<string, string>  $filters
     */
    public function paginate(array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->shipments->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{count: int, net_kg: int, customer: float, carrier: float, profit: float}
     */
    public function totals(array $filters): array
    {
        return $this->shipments->totals($filters);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function searchOptions(): array
    {
        return [
            'customers' => $this->parties->options(PartyRole::Customer),
            'carriers' => $this->parties->options(PartyRole::VehicleOwner),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function formOptions(?Shipment $shipment = null): array
    {
        return [
            'parties' => $this->parties->options(include: array_filter([$shipment?->customer_party_id, $shipment?->commission_party_id])),
            'vat' => Shipment::VAT_RATES,
            'withholding' => Withholding::options(),
        ];
    }

    /**
     * @return list<string>
     */
    public function addressNames(string $customerId): array
    {
        return $this->shipments->addressNames($customerId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId): Shipment
    {
        return $this->transaction(function () use ($data, $userId): Shipment {
            $attributes = $this->attributes($data);
            $this->ownerships->assertOpen(Vehicle::query()->findOrFail($attributes['vehicle_id']), Carbon::parse($attributes['shipped_on']));

            /** @var Shipment $shipment */
            $shipment = $this->shipments->create([...$attributes, 'created_by' => $userId]);
            $this->ledger->sync($shipment);

            return $shipment;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shipment $shipment, array $data): Shipment
    {
        return $this->transaction(function () use ($shipment, $data): Shipment {
            $attributes = $this->attributes($data);
            $this->ownerships->assertOpen($shipment->vehicle, $shipment->shipped_on, $shipment->party_id);
            $this->ownerships->assertOpen(Vehicle::query()->findOrFail($attributes['vehicle_id']), Carbon::parse($attributes['shipped_on']));

            /** @var Shipment $updated */
            $updated = $this->shipments->update($shipment, $attributes);
            $this->ledger->sync($updated);

            return $updated;
        });
    }

    public function delete(Shipment $shipment): void
    {
        $this->transaction(function () use ($shipment): void {
            $this->ownerships->assertOpen($shipment->vehicle, $shipment->shipped_on, $shipment->party_id);
            $this->ledger->void($shipment);
            $this->shipments->delete($shipment);
        });
    }

    /**
     * The carrier is the plate's owner that day. Each side's base is net kg / 1000 × its price per
     * ton; VAT is on the base and withholding is the given share of that VAT. A new delivery address
     * is saved under the customer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $date = Carbon::parse($data['shipped_on'])->startOfDay();
        $vehicle = Vehicle::findByPlate((string) $data['plate']) ?? throw new OperationRejectedException('Plaka bulunamadı.');
        $carrier = $vehicle->ownershipOn($date)?->party_id
            ?? throw new OperationRejectedException(sprintf('%s aracının %s tarihinde kayıtlı sahibi yok.', $vehicle->plate, $date->format('d.m.Y')));

        $tons = (int) $data['net_kg'] / 1000;
        $side = function (string $price, string $vatRate, string $withholding) use ($data, $tons): array {
            $base = round($tons * (float) $data[$price], 2);
            $vat = round($base * (float) $data[$vatRate] / 100, 2);
            $withheld = round($vat * (float) $data[$withholding], 2);

            return [LedgerLine::money($base), LedgerLine::money($vat), LedgerLine::money($withheld), LedgerLine::money($base + $vat - $withheld)];
        };

        [$carrierBase, $carrierVat, $carrierWithheld, $carrierNet] = $side('carrier_price', 'carrier_vat_rate', 'carrier_withholding_rate');
        [$saleBase, $saleVat, $saleWithheld, $customerNet] = $side('sale_price', 'vat_rate', 'withholding_rate');

        $commissionParty = $data['commission_party_id'] ?? null;
        $this->assignRoles($data['customer_party_id'], $commissionParty);

        return [
            'shipped_on' => $date->toDateString(),
            'party_id' => $carrier,
            'vehicle_id' => $vehicle->id,
            'driver_name' => $data['driver_name'] ?? null,
            'driver_amount' => LedgerLine::money($data['driver_amount'] ?? 0),
            'waybill_no' => $data['waybill_no'] ?? null,
            'loading_place' => $data['loading_place'],
            'scale_ticket_no' => $data['scale_ticket_no'] ?? null,
            'net_kg' => (int) $data['net_kg'],
            'customer_party_id' => $data['customer_party_id'],
            'delivery_address_id' => $this->addressId($data['customer_party_id'], $data['delivery_address'] ?? null),
            'carrier_price' => $data['carrier_price'],
            'carrier_vat_rate' => $data['carrier_vat_rate'],
            'carrier_withholding_rate' => $data['carrier_withholding_rate'],
            'carrier_amount' => $carrierBase,
            'carrier_vat_amount' => $carrierVat,
            'carrier_withholding_amount' => $carrierWithheld,
            'carrier_net_amount' => $carrierNet,
            'carrier_note' => $data['carrier_note'] ?? null,
            'sale_price' => $data['sale_price'],
            'vat_rate' => $data['vat_rate'],
            'withholding_rate' => $data['withholding_rate'],
            'sale_amount' => $saleBase,
            'vat_amount' => $saleVat,
            'withholding_amount' => $saleWithheld,
            'customer_amount' => $customerNet,
            'customer_note' => $data['customer_note'] ?? null,
            'commission_party_id' => $commissionParty,
            'commission_amount' => $commissionParty ? LedgerLine::money($data['commission_amount'] ?? 0) : 0,
            'commission_note' => $commissionParty ? ($data['commission_note'] ?? null) : null,
        ];
    }

    private function addressId(string $customerId, ?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        return DeliveryAddress::query()->firstOrCreate(['party_id' => $customerId, 'name' => $name])->id;
    }

    private function assignRoles(string $customerId, ?string $commissionId): void
    {
        Party::query()->findOrFail($customerId)->assignRole(PartyRole::Customer);

        if ($commissionId) {
            Party::query()->findOrFail($commissionId)->assignRole(PartyRole::Commission);
        }

        FormOptionsCache::flush(FormOptionsCache::PARTIES);
    }
}

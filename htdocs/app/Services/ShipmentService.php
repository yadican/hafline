<?php

namespace App\Services;

use App\Enums\MaterialUnit;
use App\Enums\PartyRole;
use App\Enums\ShipmentKind;
use App\Enums\Withholding;
use App\Exceptions\OperationRejectedException;
use App\Models\DeliveryAddress;
use App\Models\Material;
use App\Models\Quarry;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Repositories\DeliveryAddressRepository;
use App\Repositories\MaterialRepository;
use App\Repositories\PartyRepository;
use App\Repositories\QuarryRepository;
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
        private QuarryRepository $quarries,
        private MaterialRepository $materials,
        private DeliveryAddressRepository $addresses,
        private PartyRepository $parties,
        private ShipmentPricing $pricing,
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
     * @return array{count: int, quantity: float, sale: float, customer: float, carrier: float, quarry: float, commission: float, profit: float}
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
            'kinds' => ShipmentKind::options(),
            'customers' => $this->parties->options(PartyRole::Customer),
            'carriers' => $this->parties->options(PartyRole::VehicleOwner),
            'quarries' => $this->quarries->options(),
            'materials' => $this->materials->options(),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function formOptions(?Shipment $shipment = null): array
    {
        return [
            'kinds' => ShipmentKind::options(),
            'quarries' => $this->quarries->options($shipment?->quarry_id),
            'materials' => $this->materials->options($shipment?->material_id),
            'units' => $this->materials->units(),
            'addresses' => $this->addresses->options($shipment?->delivery_address_id),
            'commissions' => $this->parties->options(PartyRole::Commission, $shipment?->commission_party_id),
            'vat' => Shipment::VAT_RATES,
            'withholding' => Withholding::options(),
        ];
    }

    /**
     * @return array{sale_price: ?string, carrier_price: ?string, quarry_price: ?string}
     */
    public function defaultPrices(ShipmentKind $kind, ?string $quarryId, ?string $materialId, ?string $addressId, Carbon $on): array
    {
        return $this->pricing->defaults($kind, $quarryId, $materialId, $addressId, $on);
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
     * The carrier is the plate's owner that day and the customer is the delivery address's cari.
     * Blank prices come from the price lists of the shipment date.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $kind = ShipmentKind::from($data['kind']);
        $date = Carbon::parse($data['shipped_on'])->startOfDay();
        $vehicle = Vehicle::findByPlate((string) $data['plate']) ?? throw new OperationRejectedException('Plaka bulunamadı.');
        $carrier = $vehicle->ownershipOn($date)?->party_id
            ?? throw new OperationRejectedException(sprintf('%s aracının %s tarihinde kayıtlı sahibi yok.', $vehicle->plate, $date->format('d.m.Y')));
        $quarry = Quarry::query()->findOrFail($data['quarry_id']);
        $address = DeliveryAddress::query()->findOrFail($data['delivery_address_id']);

        if ($kind === ShipmentKind::MaterialSale && ! $quarry->party_id) {
            throw new OperationRejectedException(sprintf('%s için cari tanımlı değil; malzeme satışlı seferde ocağa alacak yazılamaz.', $quarry->name));
        }

        $defaults = $this->pricing->defaults($kind, $quarry->id, $data['material_id'], $address->id, $date);
        $price = function (string $key, string $label) use ($data, $defaults): string {
            return $data[$key] ?? $defaults[$key] ?? throw new OperationRejectedException("{$label} girin veya fiyat listesine ekleyin.");
        };

        $material = Material::query()->findOrFail($data['material_id']);
        $netKg = isset($data['net_kg']) ? (int) $data['net_kg'] : null;
        $quantity = $material->unit === MaterialUnit::Ton
            ? round(($netKg ?? throw new OperationRejectedException('Net miktarı (kg) girin.')) / 1000, 3)
            : (float) $data['quantity'];
        $salePrice = $price('sale_price', 'Müşteri birim fiyatını');
        $carrierPrice = $price('carrier_price', 'Nakliyeci birim fiyatını');
        $quarryPrice = $kind === ShipmentKind::MaterialSale ? $price('quarry_price', 'Ocak alış fiyatını') : '0';
        $vatRate = (float) ($data['vat_rate'] ?? 0);
        $withholding = (float) ($data['withholding_rate'] ?? 0);
        $carrierVatRate = (float) ($data['carrier_vat_rate'] ?? 0);
        $carrierWithholding = (float) ($data['carrier_withholding_rate'] ?? 0);

        $sale = round($quantity * (float) $salePrice, 2);
        $vat = round($sale * $vatRate / 100, 2);
        $withheld = round($vat * $withholding, 2);
        $carrierBase = round($quantity * (float) $carrierPrice, 2);
        $carrierVat = round($carrierBase * $carrierVatRate / 100, 2);
        $carrierWithheld = round($carrierVat * $carrierWithholding, 2);

        $commissionParty = $data['commission_party_id'] ?? null;

        return [
            'kind' => $kind,
            'shipped_on' => $date->toDateString(),
            'vehicle_id' => $vehicle->id,
            'party_id' => $carrier,
            'quarry_id' => $quarry->id,
            'quarry_party_id' => $kind === ShipmentKind::MaterialSale ? $quarry->party_id : null,
            'material_id' => $material->id,
            'delivery_address_id' => $address->id,
            'customer_party_id' => $address->party_id,
            'net_kg' => $netKg,
            'quantity' => $quantity,
            'sale_price' => $salePrice,
            'carrier_price' => $carrierPrice,
            'quarry_price' => $quarryPrice,
            'vat_rate' => $vatRate,
            'withholding_rate' => $withholding,
            'carrier_vat_rate' => $carrierVatRate,
            'carrier_withholding_rate' => $carrierWithholding,
            'sale_amount' => LedgerLine::money($sale),
            'vat_amount' => LedgerLine::money($vat),
            'withholding_amount' => LedgerLine::money($withheld),
            'customer_amount' => LedgerLine::money($sale + $vat - $withheld),
            'carrier_amount' => LedgerLine::money($carrierBase),
            'carrier_vat_amount' => LedgerLine::money($carrierVat),
            'carrier_withholding_amount' => LedgerLine::money($carrierWithheld),
            'carrier_net_amount' => LedgerLine::money($carrierBase + $carrierVat - $carrierWithheld),
            'quarry_amount' => LedgerLine::money(round($quantity * (float) $quarryPrice, 2)),
            'commission_party_id' => $commissionParty,
            'commission_amount' => $commissionParty ? LedgerLine::money($data['commission_amount'] ?? 0) : 0,
            'waybill_no' => $data['waybill_no'] ?? null,
            'scale_ticket_no' => $data['scale_ticket_no'] ?? null,
            'quarry_invoice_no' => $data['quarry_invoice_no'] ?? null,
            'description' => $data['description'] ?? null,
        ];
    }
}

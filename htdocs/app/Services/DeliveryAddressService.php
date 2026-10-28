<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\DeliveryAddress;
use App\Models\Shipment;
use App\Repositories\DeliveryAddressRepository;
use App\Repositories\PartyRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryAddressService extends Service
{
    public function __construct(
        private DeliveryAddressRepository $addresses,
        private PartyRepository $parties,
    ) {}

    /**
     * @param  array{q?: string, party?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->addresses->search($filters, $sort, $perPage);
    }

    /**
     * @return array<string, string>
     */
    public function partyOptions(?DeliveryAddress $address = null): array
    {
        return $this->parties->options(include: $address?->party_id);
    }

    /**
     * @return array<string, string>
     */
    public function customerOptions(): array
    {
        return $this->parties->options(PartyRole::Customer);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DeliveryAddress
    {
        return $this->transaction(function () use ($attributes): DeliveryAddress {
            /** @var DeliveryAddress $address */
            $address = $this->addresses->create($attributes);
            $this->assignRole($address);

            return $address;
        });
    }

    /**
     * The customer is fixed once shipments were billed to this address.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(DeliveryAddress $address, array $attributes): DeliveryAddress
    {
        if ($attributes['party_id'] !== $address->party_id && Shipment::query()->where('delivery_address_id', $address->id)->exists()) {
            throw new OperationRejectedException('Bu sevk yerine sefer girilmiş; müşterisi değiştirilemez.');
        }

        return $this->transaction(function () use ($address, $attributes): DeliveryAddress {
            /** @var DeliveryAddress $updated */
            $updated = $this->addresses->update($address, $attributes);
            $this->assignRole($updated);

            return $updated;
        });
    }

    public function delete(DeliveryAddress $address): void
    {
        if (Shipment::query()->where('delivery_address_id', $address->id)->exists()) {
            throw new OperationRejectedException('Bu sevk yerine sefer girilmiş; silmek yerine pasife alın.');
        }

        $this->transaction(fn () => $this->addresses->delete($address));
    }

    private function assignRole(DeliveryAddress $address): void
    {
        $address->party?->assignRole(PartyRole::Customer);
        FormOptionsCache::flush(FormOptionsCache::PARTIES);
    }
}

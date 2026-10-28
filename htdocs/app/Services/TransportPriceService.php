<?php

namespace App\Services;

use App\Enums\TransportPriceKind;
use App\Models\DeliveryAddress;
use App\Models\Quarry;
use App\Models\TransportPrice;
use Illuminate\Support\Collection;

/**
 * Price lists are kept on the quarry card (quarry purchase and carrier prices) and on the delivery
 * address card (sale prices). A new price starts on its date; the earlier one stays for older shipments.
 */
class TransportPriceService extends Service
{
    /**
     * @return Collection<int, TransportPrice>
     */
    public function forQuarry(Quarry $quarry): Collection
    {
        return $quarry->prices()->with(['material', 'deliveryAddress.party'])->orderBy('kind')->orderByDesc('valid_from')->get();
    }

    /**
     * @return Collection<int, TransportPrice>
     */
    public function forAddress(DeliveryAddress $address): Collection
    {
        return $address->prices()->with('material')->orderByDesc('valid_from')->get();
    }

    /**
     * The same key and start date is replaced rather than duplicated.
     *
     * @param  array{kind: string, quarry_id?: ?string, delivery_address_id?: ?string, material_id?: ?string, price: string, valid_from: string}  $data
     */
    public function save(array $data): TransportPrice
    {
        $kind = TransportPriceKind::from($data['kind']);
        $keys = [
            'kind' => $kind,
            'quarry_id' => $kind === TransportPriceKind::Sale ? null : $data['quarry_id'],
            'delivery_address_id' => $kind === TransportPriceKind::Quarry ? null : $data['delivery_address_id'],
            'material_id' => $kind === TransportPriceKind::Freight ? null : $data['material_id'],
            'valid_from' => $data['valid_from'],
        ];

        return $this->transaction(function () use ($keys, $data): TransportPrice {
            TransportPrice::query()->where($keys)->delete();

            /** @var TransportPrice */
            return TransportPrice::query()->create([...$keys, 'price' => $data['price']]);
        });
    }

    public function delete(TransportPrice $price): void
    {
        $price->delete();
    }
}

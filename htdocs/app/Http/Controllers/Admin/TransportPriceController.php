<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransportPriceKind;
use App\Http\Requests\Admin\TransportPriceRequest;
use App\Models\TransportPrice;
use App\Services\TransportPriceService;
use Illuminate\Http\RedirectResponse;

/**
 * Sale prices belong to the delivery address card, quarry and carrier prices to the quarry card;
 * editing the card is what allows changing its prices.
 */
class TransportPriceController extends AdminController
{
    public function __construct(private TransportPriceService $prices) {}

    public function store(TransportPriceRequest $request): RedirectResponse
    {
        $kind = TransportPriceKind::from($request->validated('kind'));
        $this->authorizeCard($kind);

        return $this->complete(
            fn () => $this->prices->save($request->validated()),
            ...$this->card($kind, $request->validated('quarry_id'), $request->validated('delivery_address_id')),
        );
    }

    public function destroy(TransportPrice $transportPrice): RedirectResponse
    {
        $this->authorizeCard($transportPrice->kind);

        return $this->complete(
            fn () => $this->prices->delete($transportPrice),
            ...$this->card($transportPrice->kind, $transportPrice->quarry_id, $transportPrice->delivery_address_id),
        );
    }

    private function authorizeCard(TransportPriceKind $kind): void
    {
        $this->authorize($kind === TransportPriceKind::Sale ? 'edit-delivery-address' : 'edit-quarry');
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function card(TransportPriceKind $kind, ?string $quarryId, ?string $addressId): array
    {
        return $kind === TransportPriceKind::Sale
            ? ['admin.delivery-addresses.edit', 'Fiyat listesi güncellendi.', [(string) $addressId]]
            : ['admin.quarries.edit', 'Fiyat listesi güncellendi.', [(string) $quarryId]];
    }
}

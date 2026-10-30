<?php

namespace Database\Seeders\LoadTest;

use App\Enums\AccountType;
use App\Enums\InvoiceDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\Invoice;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Shipment;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Models\VehicleReceivable;
use App\Repositories\VehicleAccountRepository;
use App\Services\CariEntryService;
use App\Services\VehicleOwnershipService;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Support\Carbon;

/**
 * Closes every transfer the way the transfer screen does.
 *
 * Catalog transfers are written before any ticket exists, so their previous owners are closed here
 * once all documents are in: the plate balance is carried to the new owner. A few live transfers
 * then run through the service a few weeks back: half carry the balance, half settle with the
 * previous owner first (payment or collection) and transfer with nothing left to carry.
 */
final class TransferStage
{
    private const LIVE_TRANSFERS = 6;

    private const LIVE_DAYS_BACK = 20;

    public function __construct(
        private Progress $progress,
        private VehicleOwnershipService $ownerships,
        private CariEntryService $entries,
        private VehicleAccountRepository $accounts,
    ) {}

    public function run(Carbon $to): void
    {
        $this->progress->stage('Devir kapanışları');

        $carried = 0;
        $open = VehicleOwnership::query()
            ->whereNull('carried_from_party_id')
            ->with('vehicle')
            ->orderBy('started_on')
            ->get();

        foreach ($open as $ownership) {
            if ($ownership->vehicle->ownerships()->where('started_on', '<', $ownership->started_on)->exists()) {
                $this->ownerships->carryPreviousBalance($ownership);
                $carried++;
            }
        }

        $this->progress->note(sprintf('Katalog devirleri: %d devirde eski sahibin plaka bakiyesi yeni sahibe aktarıldı', $carried));

        $on = $to->copy()->subDays(self::LIVE_DAYS_BACK)->startOfDay();
        $owners = Party::query()->withRole(PartyRole::VehicleOwner)->get();
        $vehicles = Vehicle::query()
            ->has('ownerships', '=', 1)
            ->whereHas('ownerships', fn ($query) => $query->whereDate('started_on', '<', $on->copy()->subDay()->toDateString()))
            ->inRandomOrder()
            ->limit(self::LIVE_TRANSFERS)
            ->get();

        foreach ($vehicles->values() as $index => $vehicle) {
            $seller = $vehicle->currentOwnership->party;
            $buyer = $owners->reject(fn (Party $owner) => $owner->id === $seller->id)->random();
            $carry = $index % 2 === 0;

            if (! $carry) {
                $this->settleWithSeller($vehicle, $seller, $on);
            }

            $ownership = $this->ownerships->transfer($vehicle, $buyer, $on, $carry);

            $this->progress->note(sprintf(
                '%s: %s → %s (%s) · %s',
                $vehicle->plate,
                $seller->name,
                $buyer->name,
                $on->format('d.m.Y'),
                $carry
                    ? 'bakiye aktarıldı: '.number_format((float) $ownership->carried_balance, 2, ',', '.').' TL'
                    : 'eski sahiple hesaplaşıldı',
            ));
        }

        $this->progress->done(sprintf('%d katalog + %d canlı devir kapatıldı', $carried, $vehicles->count()));
    }

    /**
     * Pays off (or collects) what the seller will be left with on the plate once the work from the
     * transfer date onward has moved to the buyer.
     */
    private function settleWithSeller(Vehicle $vehicle, Party $seller, Carbon $on): void
    {
        $since = $on->toDateString();
        $moving = fn ($query, string $column, string $dateColumn): float => (float) $query
            ->where('vehicle_id', $vehicle->id)
            ->where('party_id', $seller->id)
            ->where($dateColumn, '>=', $dateColumn === 'hauled_at' ? $on : $since)
            ->sum($column);

        $movingSales = (float) Invoice::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('party_id', $seller->id)
            ->where('is_draft', false)
            ->where('direction', InvoiceDirection::Sale)
            ->whereDate('issued_on', '>=', $since)
            ->sum('total');

        $left = round(
            $this->accounts->balance($vehicle->id, $seller->id)
            - $moving(Haul::query(), 'price', 'hauled_at')
            - $moving(MachineWork::query(), 'amount', 'worked_on')
            - $moving(VehicleReceivable::query(), 'amount', 'occurred_on')
            - $moving(Shipment::query(), 'carrier_net_amount', 'shipped_on')
            - $moving(Shipment::query(), 'driver_amount', 'shipped_on')
            + $moving(FuelReceipt::query(), 'amount', 'issued_on')
            + $movingSales,
            2,
        );

        if ($left === 0.0) {
            return;
        }

        $this->entries->create([
            'kind' => $left > 0 ? 'vehicle_payment' : 'vehicle_receivable',
            'plate' => $vehicle->plate,
            'amount' => number_format(abs($left), 2, '.', ''),
            'occurred_on' => $on->copy()->subDay()->toDateString(),
            'payment_method' => PaymentMethod::BankTransfer->value,
            'bank_id' => Bank::query()->where('active', true)->where('type', AccountType::Bank)->inRandomOrder()->value('id'),
            'description' => 'Devir hesaplaşması',
        ], null);
    }
}

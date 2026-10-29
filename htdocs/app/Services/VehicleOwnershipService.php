<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\CompanyTransaction;
use App\Models\Party;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use App\Repositories\FuelReceiptRepository;
use App\Repositories\HaulRepository;
use App\Repositories\MachineWorkRepository;
use App\Repositories\ShipmentRepository;
use App\Repositories\VehicleAccountRepository;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\MachineWorkLedger;
use App\Services\Ledger\OwnershipTransferLedger;
use App\Services\Ledger\ShipmentLedger;
use Carbon\CarbonInterface;

/**
 * Ownership periods are contiguous: a transfer closes the open period the day before the new
 * one starts. Hauls, shipments, machine work, fuel receipts and plate receivables from the affected dates move
 * to the right owner, ledger included; payments stay with whoever was paid. The previous owner's
 * balance on the plate is then closed, either already settled (must be zero) or carried to the new
 * owner, and everything before the transfer date is locked.
 */
class VehicleOwnershipService extends Service
{
    public function __construct(
        private HaulRepository $hauls,
        private HaulLedger $ledger,
        private MachineWorkRepository $works,
        private MachineWorkLedger $workLedger,
        private FuelReceiptRepository $receipts,
        private FuelReceiptLedger $receiptLedger,
        private Ledger $postings,
        private VehicleAccountRepository $accounts,
        private OwnershipTransferLedger $transferLedger,
        private ShipmentRepository $shipments,
        private ShipmentLedger $shipmentLedger,
        private FinancialInstrumentService $instruments,
    ) {}

    /**
     * Caller provides the transaction (used while creating the vehicle).
     */
    public function open(Vehicle $vehicle, Party $party, CarbonInterface $from): VehicleOwnership
    {
        $party->assignRole(PartyRole::VehicleOwner);
        FormOptionsCache::flush(FormOptionsCache::PARTIES);

        /** @var VehicleOwnership */
        return $vehicle->ownerships()->create([
            'party_id' => $party->id,
            'started_on' => $from->toDateString(),
        ]);
    }

    /**
     * A plate opened under the temporary cari belongs to its real owner from the first day, so the
     * period itself changes hands: every ticket, receivable and payment on it moves with the ledger.
     */
    public function reassignTemporary(Vehicle $vehicle, Party $party): VehicleOwnership
    {
        return $this->transaction(function () use ($vehicle, $party): VehicleOwnership {
            /** @var VehicleOwnership|null $current */
            $current = $vehicle->currentOwnership()->lockForUpdate()->first();

            if (! $current?->party?->isTemporary()) {
                throw new OperationRejectedException('Bu plaka Geçici Cari\'de değil.');
            }

            if ($party->isSystem()) {
                throw new OperationRejectedException('Plaka gerçek bir cariye aktarılmalı.');
            }

            $party->assignRole(PartyRole::VehicleOwner);
            FormOptionsCache::flush(FormOptionsCache::PARTIES);

            $current->update(['party_id' => $party->id]);
            $this->reassignHauls($vehicle, $current->started_on);

            $payments = VehiclePayment::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereDate('occurred_on', '>=', $current->started_on->toDateString())
                ->lazyById();

            foreach ($payments as $payment) {
                if ($payment->party_id !== $party->id) {
                    $payment->update(['party_id' => $party->id]);
                    $this->postings->moveTo($payment, $party->id);
                    $this->instruments->followEntry($payment, $party->id);
                }
            }

            CompanyTransaction::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('owner_party_id', '<>', $party->id)
                ->whereDate('occurred_on', '>=', $current->started_on->toDateString())
                ->update(['owner_party_id' => $party->id]);

            return $current;
        });
    }

    /**
     * @param  bool  $carryBalance  true: the previous owner's plate balance moves to the new owner;
     *                              false: it was settled first and must already be zero.
     */
    public function transfer(Vehicle $vehicle, Party $party, CarbonInterface $on, bool $carryBalance = true): VehicleOwnership
    {
        return $this->transaction(function () use ($vehicle, $party, $on, $carryBalance): VehicleOwnership {
            $current = $vehicle->currentOwnership()->lockForUpdate()->first();

            if ($current?->party_id === $party->id) {
                throw new OperationRejectedException('Araç zaten bu cariye kayıtlı.');
            }

            if ($current && $on->lte($current->started_on)) {
                throw new OperationRejectedException(sprintf(
                    'Devir tarihi, mevcut sahipliğin başlangıcından (%s) sonra olmalı.',
                    $current->started_on->format('d.m.Y'),
                ));
            }

            $current?->update(['ended_on' => $on->copy()->subDay()->toDateString()]);

            $ownership = $this->open($vehicle, $party, $on);
            $this->reassignHauls($vehicle, $on);

            if ($current) {
                $this->closePreviousBalance($ownership, $current->party_id, $carryBalance);
            }

            return $ownership;
        });
    }

    /**
     * Posts the carry-over for a transfer whose ownership rows already exist (load test data).
     */
    public function carryPreviousBalance(VehicleOwnership $ownership): void
    {
        $previous = $ownership->vehicle->ownerships()
            ->where('started_on', '<', $ownership->started_on)
            ->first();

        if ($previous) {
            $this->transaction(fn () => $this->closePreviousBalance($ownership, $previous->party_id, true));
        }
    }

    /**
     * What the current owner would hand over on a transfer today: their balance on the plate.
     */
    public function currentBalance(Vehicle $vehicle): ?float
    {
        $current = $vehicle->currentOwnership;

        return $current ? $this->accounts->balance($vehicle->id, $current->party_id, $current->id) : null;
    }

    /**
     * Only the latest transfer can be undone; the previous owner becomes current again.
     */
    public function undo(Vehicle $vehicle, VehicleOwnership $ownership): void
    {
        $this->transaction(function () use ($vehicle, $ownership): void {
            $previous = $vehicle->ownerships()
                ->where('started_on', '<', $ownership->started_on)
                ->lockForUpdate()
                ->first();

            if ($ownership->ended_on !== null || ! $previous) {
                throw new OperationRejectedException('Yalnızca son devir geri alınabilir; aracın ilk sahipliği silinemez.');
            }

            // Payments stay with whoever was paid; once the period is gone they would sit on a party that never owned the plate.
            $paid = VehiclePayment::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('party_id', $ownership->party_id)
                ->whereDate('occurred_on', '>=', $ownership->started_on->toDateString())
                ->count();

            if ($paid > 0) {
                throw new OperationRejectedException(sprintf(
                    'Bu devirden sonra yeni sahibe %d plaka ödemesi yapılmış; devir geri alınırsa bu ödemeler plakanın sahibi olmayan bir caride kalır. Önce ödemeleri silin ya da düzeltin.',
                    $paid,
                ));
            }

            $from = $ownership->started_on;
            $this->transferLedger->void($ownership);
            $ownership->delete();
            $previous->update(['ended_on' => null]);

            $this->reassignHauls($vehicle, $from);
        });
    }

    /**
     * Rejects a change to the vehicle's documents in a period a transfer has closed: anything dated
     * before the current ownership, or a document still on an earlier owner's account.
     */
    public function assertOpen(Vehicle $vehicle, CarbonInterface $date, ?string $partyId = null): void
    {
        $closedBefore = $vehicle->closedBefore();

        if ($closedBefore === null) {
            return;
        }

        $onEarlierOwner = $partyId !== null && $partyId !== $vehicle->ownershipOn($date)?->party_id;

        if ($date->lt($closedBefore) || $onEarlierOwner) {
            throw new OperationRejectedException(sprintf(
                '%s plakasının %s öncesi hesabı devir ile kapatıldı; bu kayıt eklenemez, değiştirilemez veya silinemez. Düzeltmek için son devri geri alın.',
                $vehicle->plate,
                $closedBefore->format('d.m.Y'),
            ));
        }
    }

    private function closePreviousBalance(VehicleOwnership $ownership, string $previousPartyId, bool $carryBalance): void
    {
        $balance = $this->accounts->balance($ownership->vehicle_id, $previousPartyId, $ownership->id);

        if (! $carryBalance && $balance !== 0.0) {
            throw new OperationRejectedException(sprintf(
                'Eski sahibin bu plakada %s TL %s var; önce ödeme veya tahsilatla kapatın ya da bakiyeyi yeni sahibe aktarın.',
                number_format(abs($balance), 2, ',', '.'),
                $balance > 0 ? 'alacağı' : 'borcu',
            ));
        }

        $ownership->update([
            'carried_from_party_id' => $previousPartyId,
            'carried_balance' => $carryBalance ? $balance : 0,
        ]);

        $this->transferLedger->sync($ownership->refresh());
    }

    private function reassignHauls(Vehicle $vehicle, CarbonInterface $from): void
    {
        foreach ($this->hauls->forVehicleSince($vehicle->id, $from) as $haul) {
            $party = $this->ownerFor($vehicle, $haul->hauled_at, $haul->ticket_no);

            if ($haul->party_id !== $party) {
                $haul->update(['party_id' => $party]);
                $this->ledger->sync($haul);
            }
        }

        foreach ($this->works->forVehicleSince($vehicle->id, $from) as $work) {
            $party = $this->ownerFor($vehicle, $work->worked_on, $work->ticket_no);

            if ($work->party_id !== $party) {
                $work->update(['party_id' => $party]);
                $this->workLedger->sync($work);
            }
        }

        foreach ($this->receipts->forVehicleSince($vehicle->id, $from) as $receipt) {
            $party = $this->ownerFor($vehicle, $receipt->issued_on, $receipt->ticket_no);

            if ($receipt->party_id !== $party) {
                $receipt->update(['party_id' => $party]);
                $this->receiptLedger->sync($receipt);
            }
        }

        foreach ($this->shipments->forVehicleSince($vehicle->id, $from) as $shipment) {
            $party = $this->ownerFor($vehicle, $shipment->shipped_on, $shipment->waybill_no);

            if ($shipment->party_id !== $party) {
                $shipment->update(['party_id' => $party]);
                $this->shipmentLedger->sync($shipment);
            }
        }

        $receivables = VehicleReceivable::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereDate('occurred_on', '>=', $from->toDateString())
            ->lazyById();

        foreach ($receivables as $receivable) {
            $party = $this->ownerFor($vehicle, $receivable->occurred_on, $receivable->description);

            if ($receivable->party_id !== $party) {
                $receivable->update(['party_id' => $party]);
                $this->postings->moveTo($receivable, $party);
            }
        }
    }

    private function ownerFor(Vehicle $vehicle, CarbonInterface $date, ?string $ticketNo): string
    {
        return $vehicle->ownershipOn($date)?->party_id ?? throw new OperationRejectedException(sprintf(
            '%s numaralı fişin tarihinde (%s) aracın sahibi kalmıyor.',
            $ticketNo,
            $date->format('d.m.Y'),
        ));
    }
}

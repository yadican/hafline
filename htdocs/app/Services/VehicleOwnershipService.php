<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\Party;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Repositories\FuelReceiptRepository;
use App\Repositories\HaulRepository;
use App\Repositories\MachineWorkRepository;
use App\Services\Ledger\FuelReceiptLedger;
use App\Services\Ledger\HaulLedger;
use App\Services\Ledger\MachineWorkLedger;
use Carbon\CarbonInterface;

/**
 * Ownership periods are contiguous: a transfer closes the open period the day before the new
 * one starts. Hauls, machine work and fuel receipts from the affected dates move to the right owner, ledger included.
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

    public function transfer(Vehicle $vehicle, Party $party, CarbonInterface $on): VehicleOwnership
    {
        return $this->transaction(function () use ($vehicle, $party, $on): VehicleOwnership {
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

            return $ownership;
        });
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

            $from = $ownership->started_on;
            $ownership->delete();
            $previous->update(['ended_on' => null]);

            $this->reassignHauls($vehicle, $from);
        });
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

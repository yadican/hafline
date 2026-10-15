<?php

namespace App\Services\Ledger;

use App\Models\MachineWork;

/**
 * A machine work ticket earns hours × hourly rate for the machine's owner of the day, and
 * hours × operator rate for an operator who is paid per ticket.
 */
class MachineWorkLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(MachineWork $work): void
    {
        $work->loadMissing('site');

        $hours = MachineWork::formatHours($work->hours);
        $lines = [
            LedgerLine::credit(
                $work->party_id,
                $work->worked_on,
                $work->total(),
                sprintf('Makine fişi %s · %s · %s saat', $work->ticket_no, $work->site?->name, $hours),
                $work->vehicle_id,
            ),
        ];

        if ($operatorTotal = $work->operatorTotal()) {
            $lines[] = LedgerLine::credit(
                $work->operator_id,
                $work->worked_on,
                $operatorTotal,
                sprintf('Makine fişi %s · %s · %s saat operatörlük', $work->ticket_no, $work->site?->name, $hours),
                $work->vehicle_id,
            );
        }

        $this->ledger->sync($work, $lines);
    }

    public function void(MachineWork $work): void
    {
        $this->ledger->void($work);
    }
}

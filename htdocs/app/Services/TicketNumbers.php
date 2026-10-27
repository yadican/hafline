<?php

namespace App\Services;

use App\Enums\TicketKind;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketNumbers
{
    /**
     * "M-20261026-183012-07": prefix, date, time and a random suffix; fits the 20 character ticket_no column.
     * Soft-deleted tickets keep their numbers.
     */
    public function next(TicketKind $kind): string
    {
        for ($i = 0; $i < 10; $i++) {
            $ticket = sprintf(
                '%s-%s-%s',
                $kind->value,
                now()->format('Ymd-His'),
                str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT),
            );

            if (! DB::table($kind->table())->where('ticket_no', $ticket)->exists()) {
                return $ticket;
            }
        }

        throw new RuntimeException('Fiş numarası üretilemedi.');
    }
}

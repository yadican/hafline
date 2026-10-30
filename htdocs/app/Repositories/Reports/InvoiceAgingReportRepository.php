<?php

namespace App\Repositories\Reports;

use App\Enums\InvoiceDirection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceAgingReportRepository
{
    /**
     * Open invoice remainings for one direction, one row per invoice.
     *
     * @return Collection<int, object{party_id: string, name: string, due_on: ?string, remaining: string}>
     */
    public function openRemainings(InvoiceDirection $direction): Collection
    {
        $paid = '(COALESCE((
            SELECT SUM(invoice_payments.amount)
            FROM invoice_payments
            WHERE invoice_payments.invoice_id = invoices.id
              AND invoice_payments.deleted_at IS NULL
        ), 0) + COALESCE((
            SELECT SUM(returns.total)
            FROM invoices returns
            WHERE returns.returns_invoice_id = invoices.id
              AND returns.deleted_at IS NULL
              AND returns.is_draft = false
        ), 0))';

        return DB::table('invoices')
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.is_draft', false)
            ->where('invoices.direction', $direction->value)
            ->whereRaw("invoices.total > {$paid}")
            ->orderBy('parties.name')
            ->orderBy('invoices.due_on')
            ->selectRaw(
                "parties.id as party_id,
                parties.name,
                invoices.due_on,
                (invoices.total - {$paid}) as remaining",
            )
            ->get();
    }
}

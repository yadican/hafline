<?php

namespace App\Services\Ledger;

use App\Models\Invoice;
use App\Models\LedgerEntry;

/**
 * An invoice is owed on the day it is issued: a purchase or sale return credits the party, a sale
 * or purchase return debits the party, both by the invoice total. A plate sale also ties the debit
 * to the vehicle (K8); it does not touch the supplier (K9). Payments are separate documents.
 */
class InvoiceLedger
{
    public function __construct(private Ledger $ledger) {}

    public function sync(Invoice $invoice): void
    {
        $description = sprintf('%s faturası %s', $invoice->direction->label(), $invoice->invoice_no)
            .($invoice->description ? ' · '.$invoice->description : '');

        $this->ledger->sync($invoice, [
            $invoice->direction->creditsParty()
                ? LedgerLine::credit($invoice->party_id, $invoice->issued_on, $invoice->total, $description, $invoice->vehicle_id)
                : LedgerLine::debit($invoice->party_id, $invoice->issued_on, $invoice->total, $description, $invoice->vehicle_id),
        ]);
    }

    public function void(Invoice $invoice): void
    {
        $this->ledger->void($invoice);
    }

    /**
     * The invoice's current row on the party's account.
     */
    public function entry(Invoice $invoice): ?LedgerEntry
    {
        return $this->ledger->live($invoice)->first();
    }
}

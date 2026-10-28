<?php

namespace App\Services\Ledger;

use App\Enums\Module;

/**
 * A document that posts to the cari ledger. Every line it posts carries its module and site.
 */
interface LedgerSource
{
    public function ledgerModule(): Module;

    public function ledgerSiteId(): ?string;
}

<?php

namespace App\Services\Ledger;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps a source document's postings in step with what it says now. Posted rows are never
 * edited: a change reverses the rows that no longer hold and posts fresh ones, so the history stays readable.
 * Callers must already be inside a database transaction.
 */
class Ledger
{
    /**
     * Only the lines that changed are reversed and re-posted; a line the source still wants stays as
     * it is, so other parties on the same document do not get cancel / re-post noise.
     *
     * @param  list<LedgerLine>  $lines
     */
    public function sync(LedgerSource&Model $source, array $lines): void
    {
        $wanted = collect($lines)
            ->reject(fn (LedgerLine $line) => $line->isZero())
            ->map(fn (LedgerLine $line) => $line->postedBy($source))
            ->values();
        $stale = [];

        foreach ($this->live($source) as $entry) {
            $key = $this->lineOf($entry)->key();
            $match = $wanted->search(fn (LedgerLine $line) => $line->key() === $key);

            if ($match === false) {
                $stale[] = $entry;
            } else {
                $wanted->forget($match);
            }
        }

        $this->reverse($stale);

        foreach ($wanted as $line) {
            LedgerEntry::query()->create([
                'party_id' => $line->partyId,
                'occurred_on' => $line->occurredOn,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'vehicle_id' => $line->vehicleId,
                'payment_method' => $line->paymentMethod,
                'cash_category_id' => $line->cashCategoryId,
                'module' => $line->module,
                'site_id' => $line->siteId,
                'bank_id' => $line->bankId,
                'created_by' => auth()->id(),
            ]);
        }
    }

    public function void(Model $source): void
    {
        $this->reverse($this->live($source));
    }

    /**
     * Re-posts the source's live lines unchanged on another party's account.
     */
    public function moveTo(LedgerSource&Model $source, string $partyId): void
    {
        $this->sync($source, $this->live($source)->map(fn (LedgerEntry $entry) => new LedgerLine(
            $partyId,
            $entry->occurred_on,
            $entry->debit,
            $entry->credit,
            $entry->description,
            $entry->vehicle_id,
            $entry->payment_method,
            $entry->cash_category_id,
            bankId: $entry->bank_id,
        ))->all());
    }

    /**
     * Rows of the source that have not been reversed and are not reversals themselves.
     *
     * @return Collection<int, LedgerEntry>
     */
    public function live(Model $source): Collection
    {
        return LedgerEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('reversal_of')
            ->whereDoesntHave('reversal')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  iterable<LedgerEntry>  $entries
     */
    private function reverse(iterable $entries): void
    {
        foreach ($entries as $entry) {
            LedgerEntry::query()->create([
                'party_id' => $entry->party_id,
                'occurred_on' => $entry->occurred_on,
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'description' => 'İptal: '.$entry->description,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'vehicle_id' => $entry->vehicle_id,
                'payment_method' => $entry->payment_method,
                'cash_category_id' => $entry->cash_category_id,
                'module' => $entry->module,
                'site_id' => $entry->site_id,
                'bank_id' => $entry->bank_id,
                'reversal_of' => $entry->id,
                'created_by' => auth()->id(),
            ]);
        }
    }

    private function lineOf(LedgerEntry $entry): LedgerLine
    {
        return new LedgerLine(
            $entry->party_id,
            $entry->occurred_on,
            $entry->debit,
            $entry->credit,
            vehicleId: $entry->vehicle_id,
            paymentMethod: $entry->payment_method,
            cashCategoryId: $entry->cash_category_id,
            module: $entry->module,
            siteId: $entry->site_id,
            bankId: $entry->bank_id,
        );
    }
}

<?php

namespace App\Services\Ledger;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps a source document's postings in step with what it says now. Posted rows are never
 * edited: a change reverses the live rows and posts fresh ones, so the history stays readable.
 * Callers must already be inside a database transaction.
 */
class Ledger
{
    /**
     * @param  list<LedgerLine>  $lines
     */
    public function sync(Model $source, array $lines): void
    {
        $lines = array_values(array_filter($lines, fn (LedgerLine $line) => ! $line->isZero()));
        $live = $this->live($source);

        if ($this->keys($live->map(fn (LedgerEntry $entry) => $this->lineOf($entry))->all()) === $this->keys($lines)) {
            return;
        }

        $this->reverse($live);

        foreach ($lines as $line) {
            LedgerEntry::query()->create([
                'party_id' => $line->partyId,
                'occurred_on' => $line->occurredOn,
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
                'source_type' => $source->getMorphClass(),
                'source_id' => $source->getKey(),
                'vehicle_id' => $line->vehicleId,
                'created_by' => auth()->id(),
            ]);
        }
    }

    public function void(Model $source): void
    {
        $this->reverse($this->live($source));
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
     * @param  Collection<int, LedgerEntry>  $entries
     */
    private function reverse(Collection $entries): void
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
                'reversal_of' => $entry->id,
                'created_by' => auth()->id(),
            ]);
        }
    }

    private function lineOf(LedgerEntry $entry): LedgerLine
    {
        return new LedgerLine($entry->party_id, $entry->occurred_on, $entry->debit, $entry->credit, vehicleId: $entry->vehicle_id);
    }

    /**
     * @param  list<LedgerLine>  $lines
     * @return list<string>
     */
    private function keys(array $lines): array
    {
        $keys = array_map(fn (LedgerLine $line) => $line->key(), $lines);
        sort($keys);

        return $keys;
    }
}

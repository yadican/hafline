<?php

namespace Tests\Feature;

use App\Models\Haul;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_posts_lines_once_and_skips_zero_amounts(): void
    {
        $haul = Haul::factory()->create();
        $party = Party::factory()->create();
        $ledger = app(Ledger::class);
        $lines = [
            LedgerLine::credit($party->id, Carbon::parse('2026-05-01'), 1200, 'A'),
            LedgerLine::credit($party->id, Carbon::parse('2026-05-01'), 0, 'Zero'),
        ];

        $ledger->sync($haul, $lines);
        $ledger->sync($haul, $lines);

        $entry = LedgerEntry::query()->sole();
        $this->assertSame('haul', $entry->source_type);
        $this->assertSame('1200.00', $entry->credit);
        $this->assertSame('0.00', $entry->debit);
    }

    public function test_changed_lines_reverse_the_live_ones_and_keep_history(): void
    {
        $haul = Haul::factory()->create();
        $first = Party::factory()->create();
        $second = Party::factory()->create();
        $ledger = app(Ledger::class);
        $day = Carbon::parse('2026-05-01');

        $ledger->sync($haul, [LedgerLine::credit($first->id, $day, 1000, 'A')]);
        $ledger->sync($haul, [LedgerLine::credit($second->id, $day, 1000, 'A')]);

        $this->assertSame(3, LedgerEntry::query()->count());
        $reversal = LedgerEntry::query()->whereNotNull('reversal_of')->sole();
        $this->assertSame($first->id, $reversal->party_id);
        $this->assertSame('1000.00', $reversal->debit);
        $this->assertSame('İptal: A', $reversal->description);
        $this->assertCount(1, $ledger->live($haul));
        $this->assertSame($second->id, $ledger->live($haul)->sole()->party_id);

        $ledger->void($haul);

        $this->assertCount(0, $ledger->live($haul));
        $this->assertSame(0.0, (float) LedgerEntry::query()->where('party_id', $first->id)->get()->sum(fn ($e) => $e->debit - $e->credit));
        $this->assertSame(0.0, (float) LedgerEntry::query()->where('party_id', $second->id)->get()->sum(fn ($e) => $e->debit - $e->credit));
    }

    public function test_description_only_changes_post_nothing(): void
    {
        $haul = Haul::factory()->create();
        $party = Party::factory()->create();
        $ledger = app(Ledger::class);
        $day = Carbon::parse('2026-05-01');

        $ledger->sync($haul, [LedgerLine::credit($party->id, $day, 1000, 'Old')]);
        $ledger->sync($haul, [LedgerLine::credit($party->id, $day, '1000.00', 'New')]);

        $this->assertSame(1, LedgerEntry::query()->count());
    }
}

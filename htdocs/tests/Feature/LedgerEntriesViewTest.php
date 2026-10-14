<?php

namespace Tests\Feature;

use App\Models\Haul;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerEntriesViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_haul_credits_the_vehicle_and_the_dump_site(): void
    {
        $haul = Haul::factory()->create(['price' => 2500, 'dump_price' => 400]);
        Haul::factory()->create()->delete();

        $entries = DB::table('ledger_entries')
            ->where('source_table', 'hauls')
            ->orderBy('party_type')
            ->get();

        $this->assertCount(2, $entries);

        [$dumpSite, $vehicle] = $entries->all();

        $this->assertSame(['dump_site', $haul->dump_site_id, 0.0, 400.0], [$dumpSite->party_type, $dumpSite->party_id, (float) $dumpSite->debit, (float) $dumpSite->credit]);
        $this->assertSame(['vehicle', $haul->vehicle_id, 0.0, 2500.0], [$vehicle->party_type, $vehicle->party_id, (float) $vehicle->debit, (float) $vehicle->credit]);
        $this->assertSame($haul->hauled_at->toDateString(), $vehicle->occurred_on);
    }

    public function test_only_paid_fines_debit_the_vehicle_on_the_payment_date(): void
    {
        $vehicle = Vehicle::factory()->create();

        $fine = fn (array $attributes) => DB::table('vehicle_fines')->insert($attributes + [
            'id' => (string) Str::uuid7(),
            'vehicle_id' => $vehicle->id,
            'amount' => 1200,
            'occurred_on' => '2026-03-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fine(['status' => 'pending']);
        $fine(['status' => 'paid', 'paid_on' => '2026-03-20']);

        $entries = DB::table('ledger_entries')->where('source_table', 'vehicle_fines')->get();

        $this->assertCount(1, $entries);
        $this->assertSame([$vehicle->id, '2026-03-20', 1200.0, 0.0], [$entries[0]->party_id, $entries[0]->occurred_on, (float) $entries[0]->debit, (float) $entries[0]->credit]);
    }
}

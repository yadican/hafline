<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\DumpSite;
use App\Models\FinancialInstrument;
use App\Models\Party;
use App\Models\Vehicle;
use App\Services\CariEntryService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LoadTestInstrumentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        $this->catalog = new CatalogStage(new Progress(null));
    }

    public function test_with_instrument_details_fills_due_on_and_number_for_cheque_and_note(): void
    {
        $cheque = $this->catalog->withInstrumentDetails([
            'payment_method' => PaymentMethod::Cheque->value,
            'occurred_on' => '2026-02-15',
        ]);
        $note = $this->catalog->withInstrumentDetails([
            'payment_method' => PaymentMethod::PromissoryNote->value,
            'occurred_on' => '2026-02-15',
        ]);
        $cash = $this->catalog->withInstrumentDetails([
            'payment_method' => PaymentMethod::Cash->value,
            'occurred_on' => '2026-02-15',
        ]);
        $kept = $this->catalog->withInstrumentDetails([
            'payment_method' => PaymentMethod::Cheque->value,
            'occurred_on' => '2026-02-15',
            'cheque_no' => 'CK-KEEP',
            'due_on' => '2026-05-01',
        ]);

        $this->assertSame('CK000001', $cheque['cheque_no']);
        $this->assertTrue(Carbon::parse($cheque['due_on'])->gte('2026-02-15'));
        $this->assertNotSame('2026-02-15', $cheque['due_on']);

        $this->assertSame('SN000002', $note['cheque_no']);
        $this->assertTrue(Carbon::parse($note['due_on'])->gte('2026-02-15'));

        $this->assertArrayNotHasKey('cheque_no', $cash);
        $this->assertArrayNotHasKey('due_on', $cash);

        $this->assertSame(['CK-KEEP', '2026-05-01'], [$kept['cheque_no'], $kept['due_on']]);
    }

    public function test_seeded_cheque_payments_create_instruments_with_due_on_and_number(): void
    {
        $entries = app(CariEntryService::class);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 LT 38']);
        $dump = DumpSite::factory()->create();
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();

        $samples = [
            [
                'kind' => 'supplier',
                'direction' => 'debit',
                'party_id' => $supplier->id,
                'payment_method' => PaymentMethod::Cheque->value,
            ],
            [
                'kind' => 'customer',
                'direction' => 'credit',
                'party_id' => $customer->id,
                'payment_method' => PaymentMethod::PromissoryNote->value,
            ],
            [
                'kind' => 'vehicle_payment',
                'plate' => $vehicle->plate,
                'payment_method' => PaymentMethod::Cheque->value,
            ],
            [
                'kind' => 'dump_payment',
                'dump_site_id' => $dump->id,
                'payment_method' => PaymentMethod::Cheque->value,
            ],
        ];

        foreach ($samples as $sample) {
            $entries->create($this->catalog->withInstrumentDetails($this->catalog->withPaymentAccount([
                ...$sample,
                'amount' => '1000.00',
                'occurred_on' => '2026-02-15',
                'description' => '38c',
            ])), null);
        }

        $instruments = FinancialInstrument::query()->orderBy('number')->get();
        $this->assertCount(4, $instruments);

        foreach ($instruments as $instrument) {
            $this->assertNotNull($instrument->number);
            $this->assertNotSame('', $instrument->number);
            $this->assertTrue($instrument->due_on->gte($instrument->issued_on));
            $this->assertTrue($instrument->due_on->gt($instrument->issued_on));
        }
    }
}

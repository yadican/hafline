<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\DumpSiteClassPrice;
use App\Models\Employee;
use App\Models\HaulRoute;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Models\VehiclePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private Party $seller;

    private Vehicle $truck;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->yonetici()->create();
        $this->seller = Party::factory()->create();
        $this->truck = Vehicle::factory()->ownedBy($this->seller, '2026-01-01')->create(['plate' => '34 TR 100']);
        $route = HaulRoute::factory()->create();
        RouteClassPrice::query()->create(['route_id' => $route->id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 2500]);
        DumpSiteClassPrice::query()->create(['dump_site_id' => $route->dump_site_id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 400]);

        $this->actingAs($admin)
            ->post(route('admin.hauls.store'), [
                'ticket_no' => 'F-100',
                'plate' => '34 TR 100',
                'trips' => 2,
                'hauled_at' => '2026-03-10T10:00',
                'site_id' => $route->site_id,
                'dump_site_id' => $route->dump_site_id,
                'tonnage' => 18,
                'shift' => 'day',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.vehicles.ownerships.store', $this->truck), ['party_id' => Party::factory()->create()->id, 'started_on' => '2026-03-15', 'settlement' => 'carry'])
            ->assertSessionHas('success');
    }

    public function test_audit_passes_when_cari_documents_and_reports_agree(): void
    {
        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari satırlarının modülü ve şantiyesi belgeyle aynı · kontrol')
            ->expectsOutputToContain('Devirde eski sahibin plaka bakiyesi kapalı · kontrol 1 · uyumsuz 0')
            ->expectsOutputToContain('Plaka Aylık: devir, satırlar, bakiye')
            ->expectsOutputToContain('Plaka Sahibi Aylık: devir, satırlar, bakiye')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_audit_matches_payroll_postings_on_the_employee_cari(): void
    {
        $employee = Employee::factory()->create(['salary' => 30000]);

        $this->actingAs(User::query()->firstOrFail())
            ->post(route('admin.employee-payments.store'), [
                'employee_id' => $employee->id,
                'amount' => '4000',
                'payment_method' => PaymentMethod::Cash->value,
                'year' => 2026,
                'month' => 2,
                'occurred_on' => '2026-02-10',
            ])
            ->assertSessionHasNoErrors();

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari satırları kaynak belgelerle birebir · kontrol')
            ->expectsOutputToContain('Kasa kapanışı = tüm nakit hareketler · kontrol 1 · uyumsuz 0')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_owner_operating_their_own_machine_keeps_the_operator_share_off_the_plate(): void
    {
        $owner = Party::factory()->person()->withRole(PartyRole::VehicleOwner, PartyRole::MachineOperator)->create();
        $machine = Vehicle::factory()
            ->ownedBy($owner, '2026-01-01')
            ->create(['plate' => '34 MK 100', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $machine->rates()->create(['hourly_rate' => 1000, 'started_on' => '2026-01-01']);

        $this->actingAs(User::query()->firstOrFail())
            ->post(route('admin.machine-works.store'), [
                'ticket_no' => 'M-100',
                'plate' => '34 MK 100',
                'worked_on' => '2026-03-14',
                'shift' => 'day',
                'site_id' => Site::factory()->create()->id,
                'operator_id' => $owner->id,
                'operator_rate' => '150',
                'hours' => '8',
                'hourly_rate' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Plaka Bakiye = carideki araç hareketleri · kontrol 2 · uyumsuz 0')
            ->assertSuccessful();
    }

    public function test_audit_fails_when_a_posting_no_longer_matches_its_document(): void
    {
        LedgerEntry::query()->where('source_type', 'haul')->where('party_id', $this->seller->id)->first()->update(['credit' => 2400]);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari satırları kaynak belgelerle birebir · kontrol')
            ->expectsOutputToContain('Cari ile kaynaklar arasında uyumsuzluk var.')
            ->assertFailed();
    }

    public function test_audit_fails_when_a_posting_is_on_another_site_than_its_document(): void
    {
        LedgerEntry::query()->where('source_type', 'haul')->where('party_id', $this->seller->id)->first()->update(['site_id' => Site::factory()->create()->id]);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari satırlarının modülü ve şantiyesi belgeyle aynı · kontrol')
            ->expectsOutputToContain('Cari ile kaynaklar arasında uyumsuzluk var.')
            ->assertFailed();
    }

    public function test_audit_fails_when_a_closed_previous_owner_gets_a_new_movement(): void
    {
        VehiclePayment::query()->create([
            'vehicle_id' => $this->truck->id,
            'party_id' => $this->seller->id,
            'amount' => 750,
            'occurred_on' => '2026-03-12',
        ]);
        DB::table('ledger_entries')->insert([
            'id' => (string) Str::uuid(),
            'party_id' => $this->seller->id,
            'occurred_on' => '2026-03-12',
            'debit' => 750,
            'credit' => 0,
            'source_type' => 'vehicle_payment',
            'source_id' => VehiclePayment::query()->value('id'),
            'vehicle_id' => $this->truck->id,
            'created_at' => now(),
        ]);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('34 TR 100: eski sahip')
            ->assertFailed();
    }
}

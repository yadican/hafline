<?php

namespace Tests\Feature\Api;

use App\Enums\PartyRole;
use App\Models\HaulRoute;
use App\Models\HaulScan;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class FieldMachineWorkApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Party $owner;

    private Vehicle $machine;

    private HaulRoute $route;

    private Party $operator;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('data');

        $this->user = User::factory()->apiOperator()->create();
        $this->owner = Party::factory()->create(['name' => 'Makine Sahibi']);
        $this->machine = Vehicle::factory()
            ->ownedBy($this->owner)
            ->create(['plate' => '34 MK 100', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $this->machine->rates()->create(['hourly_rate' => 1500, 'started_on' => '2020-01-01']);
        $this->route = HaulRoute::factory()->create();
        $this->operator = Party::factory()->person()->withRole(PartyRole::MachineOperator)->create(['name' => 'Kemal Usta']);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_machine_work_endpoints_require_api_permission(): void
    {
        $this->getJson('/api/v1/machine-works/options')->assertUnauthorized();

        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/machine-works/options')
            ->assertForbidden();
    }

    public function test_options_list_operators_with_their_pay_rule(): void
    {
        MachineWork::factory()->create(['operator_id' => $this->operator->id, 'operator_rate' => 275, 'worked_on' => '2026-06-05']);
        $staff = Party::factory()->person()->withRole(PartyRole::Employee, PartyRole::MachineOperator)->create(['name' => 'Ali Personel']);

        $operators = collect($this->api()->getJson('/api/v1/machine-works/options')->assertOk()->json('operators'))->keyBy('id');

        $this->assertSame(['id' => $this->operator->id, 'name' => 'Kemal Usta', 'salaried' => false, 'last_rate' => '275.00'], $operators[$this->operator->id]);
        $this->assertTrue($operators[$staff->id]['salaried']);
        $this->assertNull($operators[$staff->id]['last_rate']);
    }

    public function test_machine_search_lists_only_hourly_machines(): void
    {
        Vehicle::factory()->create(['plate' => '34 MK 900']);

        $this->api()
            ->getJson('/api/v1/machine-works/machines?q=34mk')
            ->assertOk()
            ->assertJsonCount(1, 'machines')
            ->assertJsonPath('machines.0.plate', '34 MK 100')
            ->assertJsonPath('machines.0.owner_name', 'Makine Sahibi')
            ->assertJsonPath('machines.0.hourly_rate', '1500.00');
    }

    public function test_check_plate_validates_a_typed_plate(): void
    {
        $this->api()
            ->getJson('/api/v1/machine-works/check-plate?plate=34mk100')
            ->assertOk()
            ->assertJsonPath('vehicle.plate', '34 MK 100')
            ->assertJsonPath('hourly', true)
            ->assertJsonPath('owner_name', 'Makine Sahibi')
            ->assertJsonPath('hourly_rate', '1500.00')
            ->assertJsonPath('rate_required', false)
            ->assertJsonPath('can_create', true);

        Vehicle::factory()->create(['plate' => '34 TR 1']);

        $this->api()
            ->getJson('/api/v1/machine-works/check-plate?plate=34TR1')
            ->assertOk()
            ->assertJsonPath('hourly', false)
            ->assertJsonPath('can_create', false);

        $this->api()
            ->getJson('/api/v1/machine-works/check-plate?plate=99ZZ999')
            ->assertOk()
            ->assertJsonPath('vehicle.found', false)
            ->assertJsonPath('can_create', false)
            ->assertJsonPath('message', 'Kayıtlı araç bulunamadı.');
    }

    public function test_a_typed_plate_creates_a_ticket_from_the_session(): void
    {
        $sessionId = $this->lockSession();

        $response = $this->api()
            ->postJson('/api/v1/machine-works', [
                'plate' => '34mk100',
                'field_session_id' => $sessionId,
                'hours' => '4,5',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'MAKİNE ÇALIŞMA FİŞİ')
            ->assertJsonPath('data.plate', '34 MK 100')
            ->assertJsonPath('data.hourly_rate', '1500.00')
            ->assertJsonPath('data.amount', '6750.00')
            ->assertJsonPath('data.owner_name', 'Makine Sahibi')
            ->assertJsonPath('data.photo_url', null);

        $work = MachineWork::query()->sole();
        $this->assertMatchesRegularExpression('/^M-\d{8}-\d{6}-\d{2}$/', $work->ticket_no);
        $this->assertSame($this->route->site_id, $work->site_id);
        $this->assertSame('day', $work->shift->value);
        $this->assertTrue($work->worked_on->isToday());
        $this->assertSame($this->user->id, $work->created_by);
        $this->assertSame('6750.00', LedgerEntry::query()->sole()->credit);
    }

    public function test_a_scanned_plate_attaches_the_photo_to_the_ticket(): void
    {
        $this->fakeOcr('34MK100');
        $sessionId = $this->lockSession();

        $scan = $this->api()
            ->post('/api/v1/machine-works/scan', ['file' => $this->platePhoto(), 'field_session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_create', true)
            ->assertJsonPath('vehicle.plate', '34 MK 100');

        $this->api()
            ->postJson('/api/v1/machine-works', [
                'plate' => '34 MK 100',
                'scan_id' => $scan->json('scan_id'),
                'field_session_id' => $sessionId,
                'hours' => 8,
            ])
            ->assertCreated();

        $work = MachineWork::query()->sole();
        $stored = HaulScan::query()->findOrFail($scan->json('scan_id'));
        $this->assertSame(HaulScan::STATUS_ATTACHED, $stored->status);
        $this->assertSame($work->id, $stored->machine_work_id);
        $this->assertStringStartsWith('machine-photos/', $stored->path);
        Storage::disk('data')->assertExists($stored->path);

        $photoUrl = $this->api()->getJson('/api/v1/machine-works/'.$work->id)->json('data.photo_url');
        $this->assertStringContainsString('/media/'.$stored->path, $photoUrl);
        $this->get($photoUrl)->assertOk();

        $this->api()
            ->postJson('/api/v1/machine-works', [
                'plate' => '34 MK 100',
                'scan_id' => $scan->json('scan_id'),
                'field_session_id' => $sessionId,
                'hours' => 8,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scan_id');
    }

    public function test_store_applies_the_admin_rules(): void
    {
        $sessionId = $this->lockSession();
        Vehicle::factory()->create(['plate' => '34 TR 1']);
        Vehicle::factory()->create(['plate' => '34 MK 200', 'vehicle_class_id' => $this->machine->vehicle_class_id]);

        $this->api()
            ->postJson('/api/v1/machine-works', ['plate' => '34 TR 1', 'field_session_id' => $sessionId, 'hours' => 8])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->api()
            ->postJson('/api/v1/machine-works', ['plate' => '34 MK 200', 'field_session_id' => $sessionId, 'hours' => 8])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hourly_rate');

        $this->api()
            ->postJson('/api/v1/machine-works', ['plate' => '34 MK 100', 'field_session_id' => $sessionId, 'hours' => 8, 'operator_id' => $this->operator->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('operator_rate');

        $this->api()
            ->postJson('/api/v1/machine-works', ['plate' => '34 MK 100', 'field_session_id' => fake()->uuid(), 'hours' => 8])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field_session_id');

        $this->assertSame(0, MachineWork::query()->count());

        $this->api()
            ->postJson('/api/v1/machine-works', [
                'plate' => '34 MK 200',
                'field_session_id' => $sessionId,
                'hours' => 2,
                'hourly_rate' => 1750,
                'operator_id' => $this->operator->id,
                'operator_rate' => 250,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '3500.00')
            ->assertJsonPath('data.operator.name', 'Kemal Usta')
            ->assertJsonPath('data.operator_amount', '500.00');
    }

    public function test_tickets_can_be_listed_edited_and_deleted_within_the_session(): void
    {
        $sessionId = $this->lockSession();
        $workId = $this->api()
            ->postJson('/api/v1/machine-works', ['plate' => '34 MK 100', 'field_session_id' => $sessionId, 'hours' => 8])
            ->json('data.id');

        $this->api()
            ->getJson('/api/v1/machine-works/session')
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('data.0.id', $workId)
            ->assertJsonPath('data.0.hours', 8);

        $this->api()
            ->putJson('/api/v1/machine-works/'.$workId, ['plate' => '34 MK 100', 'hours' => 10])
            ->assertOk()
            ->assertJsonPath('data.hours', 10)
            ->assertJsonPath('data.amount', '15000.00');

        $this->assertSame(-15000.0, $this->ownerBalance());

        $this->api()->deleteJson('/api/v1/machine-works/'.$workId)->assertOk();

        $this->assertSoftDeleted('machine_works', ['id' => $workId]);
        $this->assertSame(0.0, $this->ownerBalance());
    }

    public function test_other_operators_cannot_see_or_change_a_ticket(): void
    {
        $this->lockSession();
        $work = MachineWork::factory()->create([
            'vehicle_id' => $this->machine->id,
            'party_id' => $this->owner->id,
            'site_id' => $this->route->site_id,
            'shift' => 'day',
            'hours' => 8,
            'worked_on' => today(),
            'created_by' => User::factory()->apiOperator()->create()->id,
        ]);

        $this->api()->getJson('/api/v1/machine-works/'.$work->id)->assertNotFound();
        $this->api()->putJson('/api/v1/machine-works/'.$work->id, ['plate' => '34 MK 100', 'hours' => 1])->assertNotFound();
        $this->api()->deleteJson('/api/v1/machine-works/'.$work->id)->assertNotFound();
        $this->api()->getJson('/api/v1/machine-works/session')->assertOk()->assertJsonCount(0, 'data');

        $this->assertNotSoftDeleted($work);
        $this->assertSame('8.00', $work->fresh()->hours);
    }

    private function api(): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    private function lockSession(): string
    {
        return $this->api()
            ->postJson('/api/v1/field-sessions', [
                'site_id' => $this->route->site_id,
                'dump_site_id' => $this->route->dump_site_id,
                'shift' => 'day',
            ])
            ->assertCreated()
            ->json('data.id');
    }

    private function ownerBalance(): float
    {
        return (float) LedgerEntry::query()
            ->where('party_id', $this->owner->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS balance')
            ->value('balance');
    }

    private function fakeOcr(string $plate): void
    {
        Http::fake([
            config('services.ocr.scan_url') => Http::response([
                'success' => true,
                'plate' => $plate,
                'confidence' => 0.9,
                'matched_regex' => true,
            ]),
        ]);
    }

    private function platePhoto(): UploadedFile
    {
        $image = imagecreatetruecolor(320, 240);
        $path = tempnam(sys_get_temp_dir(), 'plate').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'machine.png', 'image/png', null, true);
    }
}

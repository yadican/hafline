<?php

namespace Tests\Feature\Api;

use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\HaulScan;
use App\Models\RouteClassPrice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class FieldHaulApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Vehicle $vehicle;

    private HaulRoute $route;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('data');

        $this->user = User::factory()->apiOperator()->create();
        $this->vehicle = Vehicle::factory()->create(['plate' => '34 ABC 123']);
        $this->route = HaulRoute::factory()->create();

        RouteClassPrice::query()->create([
            'route_id' => $this->route->id,
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
            'price' => 2500,
        ]);

        DumpSiteClassPrice::query()->create([
            'dump_site_id' => $this->route->dump_site_id,
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
            'price' => 400,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_field_haul_endpoints_require_api_permission(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/hauls/options')
            ->assertForbidden();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/field-sessions', [
                'site_id' => $this->route->site_id,
                'dump_site_id' => $this->route->dump_site_id,
                'shift' => 'day',
            ])
            ->assertForbidden();
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

    public function test_field_session_is_locked_on_server(): void
    {
        $sessionId = $this->lockSession();

        $this->api()
            ->getJson('/api/v1/field-sessions/current')
            ->assertOk()
            ->assertJsonPath('data.id', $sessionId)
            ->assertJsonPath('data.site_id', $this->route->site_id)
            ->assertJsonPath('data.dump_site_id', $this->route->dump_site_id)
            ->assertJsonPath('data.shift', 'day')
            ->assertJsonStructure(['data' => ['site_name', 'dump_site_name', 'shift_label', 'locked_at']]);

        $this->api()
            ->deleteJson('/api/v1/field-sessions/current')
            ->assertOk();

        $this->api()
            ->getJson('/api/v1/field-sessions/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_session_without_dump_site_cannot_write_haul_tickets(): void
    {
        $sessionId = $this->api()
            ->postJson('/api/v1/field-sessions', [
                'site_id' => $this->route->site_id,
                'shift' => 'day',
            ])
            ->assertCreated()
            ->assertJsonPath('data.dump_site_id', null)
            ->json('data.id');

        $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
                'field_session_id' => $sessionId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dump_site_id');

        $this->api()
            ->postJson('/api/v1/hauls', [
                'plate' => '34 ABC 123',
                'scan_id' => fake()->uuid(),
                'field_session_id' => $sessionId,
                'tonnage' => 22,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dump_site_id');

        $this->assertSame(0, Haul::query()->count());
        $this->assertSame(0, HaulScan::query()->count());
    }

    public function test_session_dump_site_must_have_an_active_route_from_the_site(): void
    {
        $unrelated = HaulRoute::factory()->create();

        $this->api()
            ->postJson('/api/v1/field-sessions', [
                'site_id' => $this->route->site_id,
                'dump_site_id' => $unrelated->dump_site_id,
                'shift' => 'day',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dump_site_id');
    }

    public function test_options_and_dump_sites_require_auth(): void
    {
        $this->getJson('/api/v1/hauls/options')->assertUnauthorized();

        $this->api()
            ->getJson('/api/v1/hauls/options')
            ->assertOk()
            ->assertJsonStructure(['sites', 'shifts']);

        $this->api()
            ->getJson('/api/v1/hauls/dump-sites?site_id='.$this->route->site_id)
            ->assertOk()
            ->assertJsonPath('dump_sites.0.id', $this->route->dump_site_id);
    }

    public function test_scan_requires_field_session_id(): void
    {
        $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field_session_id');
    }

    public function test_scan_stores_photo_and_returns_plate(): void
    {
        Http::fake([
            config('services.ocr.scan_url') => Http::response([
                'success' => true,
                'plate' => '34ABC123',
                'confidence' => 0.91,
                'matched_regex' => true,
            ]),
        ]);

        $sessionId = $this->lockSession();

        $response = $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
                'field_session_id' => $sessionId,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_create', true)
            ->assertJsonPath('vehicle.found', true)
            ->assertJsonPath('field_session_id', $sessionId)
            ->assertJsonStructure(['scan_id', 'plate', 'confidence']);

        $scan = HaulScan::query()->findOrFail($response->json('scan_id'));
        $this->assertSame(HaulScan::STATUS_PENDING, $scan->status);
        $this->assertSame($sessionId, $scan->field_haul_session_id);
        Storage::disk('data')->assertExists($scan->path);
        $this->assertMatchesRegularExpression(
            '#^haul-scans/\d{4}/\d{2}/\d{2}/\d{2}/'.preg_quote($sessionId, '#').'-\d{17}(?:-\d+)?\.(jpg|png)$#',
            $scan->path,
        );
    }

    public function test_vehicle_search_leaves_out_machines(): void
    {
        $this->machine('34 ABC 900');

        $this->api()
            ->getJson('/api/v1/hauls/vehicles?q=34abc')
            ->assertOk()
            ->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.plate', '34 ABC 123');
    }

    public function test_check_plate_rejects_machines(): void
    {
        $this->api()
            ->getJson('/api/v1/hauls/check-plate?plate=34abc123')
            ->assertOk()
            ->assertJsonPath('vehicle.plate', '34 ABC 123')
            ->assertJsonPath('can_create', true)
            ->assertJsonPath('message', null);

        $this->machine('34 MK 100');

        $this->api()
            ->getJson('/api/v1/hauls/check-plate?plate=34MK100')
            ->assertOk()
            ->assertJsonPath('vehicle.found', true)
            ->assertJsonPath('can_create', false)
            ->assertJsonPath('message', 'Bu plaka bir makineye ait; taşıma fişi kesilemez. Makine fişi ekranını kullanın.');

        $this->api()
            ->getJson('/api/v1/hauls/check-plate?plate=99ZZ999')
            ->assertOk()
            ->assertJsonPath('vehicle.found', false)
            ->assertJsonPath('can_create', false);
    }

    public function test_scanned_machine_plate_cannot_be_saved_as_a_haul(): void
    {
        Http::fake([
            config('services.ocr.scan_url') => Http::response([
                'success' => true,
                'plate' => '34MK100',
                'confidence' => 0.9,
                'matched_regex' => true,
            ]),
        ]);

        $this->machine('34 MK 100');
        $sessionId = $this->lockSession();

        $scan = $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
                'field_session_id' => $sessionId,
            ])
            ->assertOk()
            ->assertJsonPath('vehicle.found', true)
            ->assertJsonPath('can_create', false);

        $this->api()
            ->postJson('/api/v1/hauls', [
                'plate' => '34 MK 100',
                'scan_id' => $scan->json('scan_id'),
                'field_session_id' => $sessionId,
                'tonnage' => 22,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertSame(0, Haul::query()->count());
    }

    public function test_scan_rejects_non_image_payload(): void
    {
        $sessionId = $this->lockSession();

        $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
                'field_session_id' => $sessionId,
            ])
            ->assertUnprocessable();
    }

    public function test_store_creates_haul_and_attaches_scan(): void
    {
        Http::fake([
            config('services.ocr.scan_url') => Http::response([
                'success' => true,
                'plate' => '34ABC123',
                'confidence' => 0.9,
                'matched_regex' => true,
            ]),
        ]);

        $sessionId = $this->lockSession();

        $scanId = $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
                'field_session_id' => $sessionId,
            ])
            ->json('scan_id');

        $response = $this->api()
            ->postJson('/api/v1/hauls', [
                'plate' => '34 ABC 123',
                'scan_id' => $scanId,
                'field_session_id' => $sessionId,
                'tonnage' => 22,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'SEFER HAREKET FİŞİ')
            ->assertJsonPath('data.plate', '34 ABC 123')
            ->assertJsonPath('data.tonnage', 22)
            ->assertJsonStructure(['data' => ['ticket_no', 'photo_url', 'qr_payload']]);

        $haul = Haul::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^T-\d{8}-\d{6}-\d{2}$/', $haul->ticket_no);
        $this->assertSame($this->user->id, $haul->created_by);
        $this->assertSame($sessionId, $haul->field_haul_session_id);
        $this->assertSame(22, $haul->tonnage);

        $scan = HaulScan::query()->findOrFail($scanId);
        $this->assertSame(HaulScan::STATUS_ATTACHED, $scan->status);
        $this->assertSame($haul->id, $scan->haul_id);
        Storage::disk('data')->assertExists($scan->path);

        $this->api()
            ->getJson('/api/v1/hauls/session')
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('session.site_id', $this->route->site_id)
            ->assertJsonPath('session.dump_site_id', $this->route->dump_site_id)
            ->assertJsonPath('data.0.id', $haul->id);

        $this->api()
            ->getJson('/api/v1/hauls/'.$haul->id)
            ->assertOk()
            ->assertJsonPath('data.ticket_no', $haul->ticket_no);

        $photoUrl = $response->json('data.photo_url');
        $this->assertStringContainsString('/media/'.$scan->path, $photoUrl);
        $this->get($photoUrl)->assertOk();
    }

    public function test_a_typed_plate_creates_a_haul_without_a_photo(): void
    {
        $sessionId = $this->lockSession();

        $this->api()
            ->postJson('/api/v1/hauls', [
                'plate' => '34abc123',
                'field_session_id' => $sessionId,
                'tonnage' => 18,
            ])
            ->assertCreated()
            ->assertJsonPath('data.plate', '34 ABC 123')
            ->assertJsonPath('data.tonnage', 18)
            ->assertJsonPath('data.photo_url', null);

        $haul = Haul::query()->sole();
        $this->assertSame($this->route->dump_site_id, $haul->dump_site_id);
        $this->assertSame($sessionId, $haul->field_haul_session_id);
        $this->assertSame(0, HaulScan::query()->count());
    }

    public function test_unsigned_photo_url_is_forbidden(): void
    {
        Http::fake([
            config('services.ocr.scan_url') => Http::response([
                'success' => true,
                'plate' => '34ABC123',
                'confidence' => 0.9,
                'matched_regex' => true,
            ]),
        ]);

        $sessionId = $this->lockSession();

        $scanId = $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
                'field_session_id' => $sessionId,
            ])
            ->json('scan_id');

        $haulId = $this->api()
            ->postJson('/api/v1/hauls', [
                'plate' => '34 ABC 123',
                'scan_id' => $scanId,
                'field_session_id' => $sessionId,
                'tonnage' => 22,
            ])
            ->json('data.id');

        $this->get('/api/v1/hauls/'.$haulId.'/photo')->assertForbidden();
    }

    public function test_store_requires_tonnage(): void
    {
        $sessionId = $this->lockSession();

        $this->api()
            ->postJson('/api/v1/hauls', [
                'plate' => '34 ABC 123',
                'scan_id' => fake()->uuid(),
                'field_session_id' => $sessionId,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tonnage');

        $this->assertSame(0, Haul::query()->count());
    }

    public function test_ticket_can_be_edited_and_deleted_within_the_session(): void
    {
        $this->lockSession();
        $haul = $this->sessionHaul($this->user);
        $other = Vehicle::factory()->create([
            'plate' => '34 XYZ 789',
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
        ]);

        $this->api()
            ->putJson('/api/v1/hauls/'.$haul->id, ['plate' => '34 xyz 789', 'tonnage' => 18])
            ->assertOk()
            ->assertJsonPath('data.ticket_no', $haul->ticket_no)
            ->assertJsonPath('data.plate', '34 XYZ 789')
            ->assertJsonPath('data.tonnage', 18);

        $updated = $haul->fresh();
        $this->assertSame($other->id, $updated->vehicle_id);
        $this->assertSame(18, $updated->tonnage);
        $this->assertSame('2500.00', $updated->price);
        $this->assertSame($haul->hauled_at->toIso8601String(), $updated->hauled_at->toIso8601String());

        $this->api()
            ->putJson('/api/v1/hauls/'.$haul->id, ['plate' => '00 NO 000', 'tonnage' => 18])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->machine('34 MK 100');

        $this->api()
            ->putJson('/api/v1/hauls/'.$haul->id, ['plate' => '34 MK 100', 'tonnage' => 18])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->api()->deleteJson('/api/v1/hauls/'.$haul->id)->assertOk();

        $this->assertSoftDeleted($haul);
    }

    public function test_other_operators_cannot_edit_or_delete_a_ticket(): void
    {
        $this->lockSession();
        $haul = $this->sessionHaul(User::factory()->apiOperator()->create());

        $this->api()->putJson('/api/v1/hauls/'.$haul->id, ['plate' => '34 ABC 123', 'tonnage' => 1])->assertNotFound();
        $this->api()->deleteJson('/api/v1/hauls/'.$haul->id)->assertNotFound();

        $this->assertNotSoftDeleted($haul);
        $this->assertSame(22, $haul->fresh()->tonnage);
    }

    public function test_ocr_outage_returns_bad_gateway(): void
    {
        Http::fake([
            config('services.ocr.scan_url') => Http::response('down', 503),
        ]);

        $sessionId = $this->lockSession();

        $this->api()
            ->post('/api/v1/hauls/scan', [
                'file' => $this->platePhoto(),
                'field_session_id' => $sessionId,
            ])
            ->assertStatus(502);
    }

    private function sessionHaul(User $creator): Haul
    {
        return Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'shift' => 'day',
            'tonnage' => 22,
            'hauled_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    private function machine(string $plate): Vehicle
    {
        return Vehicle::factory()->create(['plate' => $plate, 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
    }

    private function platePhoto(): UploadedFile
    {
        $image = imagecreatetruecolor(320, 240);
        $path = tempnam(sys_get_temp_dir(), 'plate').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'truck.png', 'image/png', null, true);
    }
}

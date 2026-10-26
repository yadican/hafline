<?php

namespace Tests\Feature\Api;

use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\HaulScan;
use App\Models\RouteClassPrice;
use App\Models\User;
use App\Models\Vehicle;
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

    private function platePhoto(): UploadedFile
    {
        $image = imagecreatetruecolor(320, 240);
        $path = tempnam(sys_get_temp_dir(), 'plate').'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'truck.png', 'image/png', null, true);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\HaulRoute;
use App\Models\HaulScan;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class FieldFuelReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Party $owner;

    private Vehicle $truck;

    private HaulRoute $route;

    private FuelStation $station;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('data');

        $this->user = User::factory()->apiOperator()->create();
        $this->owner = Party::factory()->create(['name' => 'Kamyon Sahibi']);
        $this->truck = Vehicle::factory()->ownedBy($this->owner)->create(['plate' => '34 AB 123']);
        $this->route = HaulRoute::factory()->create();
        $this->station = FuelStation::factory()->create(['name' => 'MERKEZ PETROL', 'unit_price' => 50]);
        $this->station->sites()->attach($this->route->site_id);

        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_fuel_receipt_endpoints_require_api_permission(): void
    {
        $this->getJson('/api/v1/fuel-receipts/options')->assertUnauthorized();

        $token = JWTAuth::fromUser(User::factory()->create());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/fuel-receipts/options')
            ->assertForbidden();
    }

    public function test_options_list_stations_serving_the_session_site(): void
    {
        $this->api()
            ->getJson('/api/v1/fuel-receipts/options')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field_session_id');

        $this->lockSession();
        $everywhere = FuelStation::factory()->create(['name' => 'YOL PETROL']);
        FuelStation::factory()->create(['name' => 'UZAK PETROL'])->sites()->attach(Site::factory()->create());
        FuelStation::factory()->create(['name' => 'KAPALI PETROL', 'active' => false]);

        $stations = $this->api()->getJson('/api/v1/fuel-receipts/options')->assertOk()->json('stations');

        $this->assertSame(['MERKEZ PETROL', 'YOL PETROL'], array_column($stations, 'name'));
        $this->assertSame('50.0000', $stations[0]['unit_price']);
        $this->assertSame($everywhere->id, $stations[1]['id']);
    }

    public function test_vehicle_search_and_plate_check(): void
    {
        $this->api()
            ->getJson('/api/v1/fuel-receipts/vehicles?q=34ab')
            ->assertOk()
            ->assertJsonCount(1, 'vehicles')
            ->assertJsonPath('vehicles.0.plate', '34 AB 123')
            ->assertJsonPath('vehicles.0.owner_name', 'Kamyon Sahibi');

        $this->api()
            ->getJson('/api/v1/fuel-receipts/check-plate?plate=34ab123')
            ->assertOk()
            ->assertJsonPath('vehicle.plate', '34 AB 123')
            ->assertJsonPath('owner_name', 'Kamyon Sahibi')
            ->assertJsonPath('can_create', true);

        Vehicle::factory()->withoutOwner()->create(['plate' => '34 XY 1']);

        $this->api()
            ->getJson('/api/v1/fuel-receipts/check-plate?plate=34XY1')
            ->assertOk()
            ->assertJsonPath('owner_on_date', false)
            ->assertJsonPath('can_create', false);

        $this->api()
            ->getJson('/api/v1/fuel-receipts/check-plate?plate=99ZZ999')
            ->assertOk()
            ->assertJsonPath('vehicle.found', false)
            ->assertJsonPath('message', 'Kayıtlı araç bulunamadı.');
    }

    public function test_a_typed_plate_creates_a_receipt_from_the_session(): void
    {
        $sessionId = $this->lockSession();

        $this->api()
            ->postJson('/api/v1/fuel-receipts', [
                'plate' => '34ab123',
                'field_session_id' => $sessionId,
                'fuel_station_id' => $this->station->id,
                'quantity' => '120,5',
                'km' => 152300,
                'received_by' => 'Mehmet',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'YAKIT FİŞİ')
            ->assertJsonPath('data.plate', '34 AB 123')
            ->assertJsonPath('data.station.name', 'MERKEZ PETROL')
            ->assertJsonPath('data.unit_price', '50.0000')
            ->assertJsonPath('data.amount', '6025.00')
            ->assertJsonPath('data.quantity_label', '120,5')
            ->assertJsonPath('data.owner_name', 'Kamyon Sahibi')
            ->assertJsonPath('data.photo_url', null);

        $receipt = FuelReceipt::query()->sole();
        $this->assertMatchesRegularExpression('/^Y-\d{8}-\d{6}-\d{2}$/', $receipt->ticket_no);
        $this->assertSame($this->route->site_id, $receipt->site_id);
        $this->assertSame('day', $receipt->shift->value);
        $this->assertTrue($receipt->issued_on->isToday());
        $this->assertSame($this->station->party_id, $receipt->fuel_party_id);
        $this->assertSame($this->user->id, $receipt->created_by);
        $this->assertSame(6025.0, $this->ownerBalance());
    }

    public function test_a_scanned_plate_attaches_the_photo_to_the_receipt(): void
    {
        $this->fakeOcr('34AB123');
        $sessionId = $this->lockSession();

        $scan = $this->api()
            ->post('/api/v1/fuel-receipts/scan', ['file' => $this->platePhoto(), 'field_session_id' => $sessionId])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_create', true)
            ->assertJsonPath('vehicle.plate', '34 AB 123');

        $payload = [
            'plate' => '34 AB 123',
            'scan_id' => $scan->json('scan_id'),
            'field_session_id' => $sessionId,
            'fuel_station_id' => $this->station->id,
            'quantity' => 80,
        ];

        $this->api()->postJson('/api/v1/fuel-receipts', $payload)->assertCreated();

        $receipt = FuelReceipt::query()->sole();
        $stored = HaulScan::query()->findOrFail($scan->json('scan_id'));
        $this->assertSame(HaulScan::STATUS_ATTACHED, $stored->status);
        $this->assertSame($receipt->id, $stored->fuel_receipt_id);
        $this->assertStringStartsWith('fuel-photos/', $stored->path);
        Storage::disk('data')->assertExists($stored->path);

        $photoUrl = $this->api()->getJson('/api/v1/fuel-receipts/'.$receipt->id)->json('data.photo_url');
        $this->assertStringContainsString('/media/'.$stored->path, $photoUrl);
        $this->get($photoUrl)->assertOk();

        $this->api()
            ->postJson('/api/v1/fuel-receipts', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scan_id');
    }

    public function test_store_applies_the_admin_rules(): void
    {
        $sessionId = $this->lockSession();
        Vehicle::factory()->withoutOwner()->create(['plate' => '34 XY 1']);
        $unpriced = FuelStation::factory()->create(['unit_price' => 0]);
        $elsewhere = FuelStation::factory()->create();
        $elsewhere->sites()->attach(Site::factory()->create());
        $base = ['plate' => '34 AB 123', 'field_session_id' => $sessionId, 'fuel_station_id' => $this->station->id, 'quantity' => 100];

        $this->api()
            ->postJson('/api/v1/fuel-receipts', [...$base, 'plate' => '34 XY 1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->api()
            ->postJson('/api/v1/fuel-receipts', [...$base, 'fuel_station_id' => $unpriced->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit_price');

        $this->api()
            ->postJson('/api/v1/fuel-receipts', [...$base, 'fuel_station_id' => $elsewhere->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fuel_station_id');

        $this->api()
            ->postJson('/api/v1/fuel-receipts', [...$base, 'field_session_id' => fake()->uuid()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('field_session_id');

        $this->assertSame(0, FuelReceipt::query()->count());

        $this->api()
            ->postJson('/api/v1/fuel-receipts', [...$base, 'fuel_station_id' => $unpriced->id, 'unit_price' => '47,25'])
            ->assertCreated()
            ->assertJsonPath('data.unit_price', '47.2500')
            ->assertJsonPath('data.amount', '4725.00');
    }

    public function test_receipts_can_be_listed_edited_and_deleted_within_the_session(): void
    {
        $sessionId = $this->lockSession();
        $receiptId = $this->api()
            ->postJson('/api/v1/fuel-receipts', [
                'plate' => '34 AB 123',
                'field_session_id' => $sessionId,
                'fuel_station_id' => $this->station->id,
                'quantity' => 100,
            ])
            ->json('data.id');

        $this->api()
            ->getJson('/api/v1/fuel-receipts/session')
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId)
            ->assertJsonPath('data.0.id', $receiptId)
            ->assertJsonPath('data.0.station_name', 'MERKEZ PETROL')
            ->assertJsonPath('data.0.quantity', 100);

        $this->api()
            ->putJson('/api/v1/fuel-receipts/'.$receiptId, ['plate' => '34 AB 123', 'fuel_station_id' => $this->station->id, 'quantity' => 150])
            ->assertOk()
            ->assertJsonPath('data.quantity', 150)
            ->assertJsonPath('data.amount', '7500.00');

        $this->assertSame(7500.0, $this->ownerBalance());

        $this->api()->deleteJson('/api/v1/fuel-receipts/'.$receiptId)->assertOk();

        $this->assertSoftDeleted('fuel_receipts', ['id' => $receiptId]);
        $this->assertSame(0.0, $this->ownerBalance());
    }

    public function test_other_operators_cannot_see_or_change_a_receipt(): void
    {
        $this->lockSession();
        $receipt = FuelReceipt::factory()->create([
            'vehicle_id' => $this->truck->id,
            'party_id' => $this->owner->id,
            'fuel_station_id' => $this->station->id,
            'site_id' => $this->route->site_id,
            'shift' => 'day',
            'quantity' => 100,
            'issued_on' => today(),
            'created_by' => User::factory()->apiOperator()->create()->id,
        ]);
        $payload = ['plate' => '34 AB 123', 'fuel_station_id' => $this->station->id, 'quantity' => 1];

        $this->api()->getJson('/api/v1/fuel-receipts/'.$receipt->id)->assertNotFound();
        $this->api()->putJson('/api/v1/fuel-receipts/'.$receipt->id, $payload)->assertNotFound();
        $this->api()->deleteJson('/api/v1/fuel-receipts/'.$receipt->id)->assertNotFound();
        $this->api()->getJson('/api/v1/fuel-receipts/session')->assertOk()->assertJsonCount(0, 'data');

        $this->assertNotSoftDeleted($receipt);
        $this->assertSame('100.00', $receipt->fresh()->quantity);
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

        return new UploadedFile($path, 'truck.png', 'image/png', null, true);
    }
}

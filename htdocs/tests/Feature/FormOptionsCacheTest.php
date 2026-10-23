<?php

namespace Tests\Feature;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Models\Party;
use App\Models\Site;
use App\Models\Vehicle;
use App\Repositories\PartyRepository;
use App\Repositories\SiteRepository;
use App\Services\SiteService;
use App\Services\VehicleOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FormOptionsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_options_are_remembered_per_object_key(): void
    {
        Cache::flush();

        $active = Site::factory()->create(['name' => 'Aktif Şantiye', 'active' => true]);
        Site::factory()->create(['name' => 'Pasif Şantiye', 'active' => false]);

        $sites = app(SiteRepository::class);

        $all = $sites->options();
        $this->assertArrayHasKey($active->id, $all);
        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'all')));

        $activeOnly = $sites->options(activeOnly: true);
        $this->assertSame([$active->id => 'Aktif Şantiye'], $activeOnly);
        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'active')));
        $this->assertFalse(Cache::has(FormOptionsCache::key(FormOptionsCache::PARTIES, 'all')));
    }

    public function test_inactive_include_is_merged_without_a_new_cache_variant(): void
    {
        Cache::flush();

        $active = Site::factory()->create(['name' => 'A Şantiye', 'active' => true]);
        $inactive = Site::factory()->create(['name' => 'Z Pasif', 'active' => false]);

        $options = app(SiteRepository::class)->options(activeOnly: true, include: $inactive->id);

        $this->assertSame([
            $active->id => 'A Şantiye',
            $inactive->id => 'Z Pasif',
        ], $options);
        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'active')));
    }

    public function test_site_create_flushes_site_option_keys(): void
    {
        Cache::flush();

        Site::factory()->create(['name' => 'Eski']);
        app(SiteRepository::class)->options();
        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'all')));

        app(SiteService::class)->create(['name' => 'Yeni Şantiye', 'active' => true, 'parties' => []]);

        $this->assertFalse(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'all')));
        $this->assertArrayHasKey(
            Site::query()->where('name', 'Yeni Şantiye')->value('id'),
            app(SiteRepository::class)->options(),
        );
    }

    public function test_bank_flush_does_not_clear_site_options(): void
    {
        Cache::flush();

        Site::factory()->create(['name' => 'Şantiye']);
        app(SiteRepository::class)->options();
        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'all')));

        FormOptionsCache::flush(FormOptionsCache::BANKS);

        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::SITES, 'all')));
    }

    public function test_vehicle_ownership_open_flushes_party_options(): void
    {
        Cache::flush();

        $owner = Party::factory()->create(['name' => 'Sahip']);
        app(PartyRepository::class)->options(PartyRole::VehicleOwner);
        $this->assertTrue(Cache::has(FormOptionsCache::key(FormOptionsCache::PARTIES, 'vehicle_owner')));

        $vehicle = Vehicle::factory()->create();
        app(VehicleOwnershipService::class)->open($vehicle, $owner, now());

        $this->assertFalse(Cache::has(FormOptionsCache::key(FormOptionsCache::PARTIES, 'vehicle_owner')));
        $this->assertContains($owner->id, array_keys(app(PartyRepository::class)->options(PartyRole::VehicleOwner)));
    }
}

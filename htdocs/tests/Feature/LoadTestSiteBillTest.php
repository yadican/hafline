<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\SiteBilling;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\Site;
use App\Services\CariEntryService;
use Database\Seeders\LoadTest\CariStage;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestSiteBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_cari_stage_bills_progress_sites_and_collects_on_site_cari_not_buyer(): void
    {
        $progressSite = $this->site(SiteBilling::Progress, 'Hakediş Şantiyesi');
        $perTripSite = $this->site(SiteBilling::PerTrip, 'Sefer Şantiyesi');

        $this->haul($progressSite, '2026-01-10', 12_000);
        $this->haul($perTripSite, '2026-01-12', 15_000);

        $catalog = new CatalogStage(new Progress(null));
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue(
            $catalog,
            collect([$progressSite, $perTripSite]),
        );

        $queue = (new ReflectionMethod(CariStage::class, 'queueSiteBilling'))->invoke(
            new CariStage(new Progress(null), app(CariEntryService::class)),
            $catalog,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-15'),
        );

        $bills = array_values(array_filter($queue, fn (array $row) => $row['kind'] === 'site_bill'));
        $collections = array_values(array_filter(
            $queue,
            fn (array $row) => ($row['kind'] ?? null) === 'site' && ($row['direction'] ?? null) === 'credit',
        ));
        $customerRows = array_values(array_filter($queue, fn (array $row) => ($row['kind'] ?? null) === 'customer'));

        $this->assertCount(1, $bills);
        $this->assertSame($progressSite->id, $bills[0]['site_id']);
        $this->assertSame('2026-01-31', $bills[0]['occurred_on']);
        $this->assertGreaterThanOrEqual(12_000, $bills[0]['amount']);
        $this->assertStringContainsString('hakedişi', (string) $bills[0]['description']);

        $this->assertNotEmpty($collections);
        $this->assertTrue(
            collect($collections)->every(fn (array $row) => in_array($row['site_id'], [$progressSite->id, $perTripSite->id], true)
                && ($row['description'] ?? null) === 'Şantiye tahsilatı'),
            'Tahsilat şantiye carisine (site credit) yazılmalı.',
        );
        $this->assertTrue(
            collect($collections)->contains(fn (array $row) => $row['site_id'] === $progressSite->id),
            'Hakediş şantiyesinden tahsilat olmalı.',
        );
        $this->assertTrue(
            collect($collections)->contains(fn (array $row) => $row['site_id'] === $perTripSite->id),
            'Sefer başına şantiyeden de tahsilat olmalı.',
        );
        $this->assertSame([], $customerRows, 'Alıcı Cari hakediş / tahsilat kalmamalı.');
    }

    public function test_cari_stage_skips_progress_bill_when_monthly_work_is_too_small(): void
    {
        $site = $this->site(SiteBilling::Progress, 'Küçük Hakediş');
        $this->haul($site, '2026-01-05', 5_000);

        $catalog = new CatalogStage(new Progress(null));
        (new ReflectionProperty(CatalogStage::class, 'sites'))->setValue($catalog, collect([$site]));

        $queue = (new ReflectionMethod(CariStage::class, 'queueSiteBilling'))->invoke(
            new CariStage(new Progress(null), app(CariEntryService::class)),
            $catalog,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertSame([], $queue);
    }

    private function site(SiteBilling $billing, string $name): Site
    {
        $party = Party::factory()->company()->withRole(PartyRole::Site)->create(['name' => $name]);

        return Site::query()->create([
            'name' => $name,
            'party_id' => $party->id,
            'billing_mode' => $billing->value,
        ]);
    }

    private function haul(Site $site, string $day, float $sitePrice): Haul
    {
        $route = HaulRoute::factory()->create(['site_id' => $site->id]);

        return Haul::factory()->create([
            'route_id' => $route->id,
            'site_id' => $site->id,
            'dump_site_id' => $route->dump_site_id,
            'site_price' => $sitePrice,
            'price' => $sitePrice * 0.7,
            'hauled_at' => Carbon::parse($day.' 10:00:00'),
        ]);
    }
}

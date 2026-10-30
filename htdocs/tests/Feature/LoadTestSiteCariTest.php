<?php

namespace Tests\Feature;

use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Enums\SiteBilling;
use App\Models\Party;
use App\Models\Site;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class LoadTestSiteCariTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_each_site_with_its_own_cari_and_a_billing_mode(): void
    {
        $catalog = new CatalogStage(new Progress(null));

        (new ReflectionProperty(CatalogStage::class, 'partiesByRole'))->setValue($catalog, [
            PartyRole::Customer->value => Party::factory()->count(5)->company()->withRole(PartyRole::Customer)->create(),
            PartyRole::Supplier->value => Party::factory()->count(3)->company()->withRole(PartyRole::Supplier)->create(),
            PartyRole::Market->value => Party::factory()->count(3)->company()->withRole(PartyRole::Market)->create(),
        ]);

        (new ReflectionMethod(CatalogStage::class, 'seedSites'))->invoke($catalog);

        $sites = $catalog->allSites();

        $this->assertCount(CatalogStage::SITE_COUNT, $sites);
        $this->assertTrue(
            $sites->every(fn (Site $site) => $site->party_id !== null
                && $site->party?->type === PartyType::Company
                && $site->party->hasRole(PartyRole::Site)
                && $site->party->name === $site->name
                && in_array($site->billing_mode, SiteBilling::cases(), true)),
            'Her şantiyenin kendi carisi (şantiye rolü) ve faturalama şekli olmalı.',
        );
        $this->assertTrue(
            $sites->contains(fn (Site $site) => $site->billing_mode === SiteBilling::PerTrip),
            'En az bir şantiye sefer başına faturalanmalı.',
        );
        $this->assertTrue(
            $sites->contains(fn (Site $site) => $site->billing_mode === SiteBilling::Progress),
            'En az bir şantiye hakediş ile faturalanmalı.',
        );
        $this->assertSame(
            CatalogStage::SITE_COUNT,
            $catalog->partiesFor(PartyRole::Site)->count(),
        );
        $this->assertSame(
            $sites->pluck('party_id')->unique()->count(),
            $sites->count(),
            'Şantiye carileri tekil olmalı.',
        );
    }
}

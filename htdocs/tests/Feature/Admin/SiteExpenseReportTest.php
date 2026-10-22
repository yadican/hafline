<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentMethod;
use App\Models\Site;
use App\Models\SiteTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->site = Site::factory()->create(['name' => 'BAKIRKÖY']);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.reports.site-expense'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_requires_site_to_run(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense'))
            ->assertOk()
            ->assertViewHas('ready', false);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense', [
                'period' => 'month',
                'year' => 2026,
                'month' => 9,
            ]))
            ->assertRedirect(route('admin.reports.site-expense'))
            ->assertSessionHasErrors(['site']);
    }

    public function test_report_shows_opening_running_balance_and_closing(): void
    {
        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 0,
            'credit' => 1000,
            'occurred_on' => '2026-08-20',
            'description' => 'Eski hakediş',
        ]);
        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::Cash,
            'debit' => 300,
            'credit' => 0,
            'occurred_on' => '2026-09-05',
            'description' => 'Gider',
        ]);
        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'payment_method' => PaymentMethod::BankTransfer,
            'debit' => 0,
            'credit' => 500,
            'occurred_on' => '2026-09-10',
            'description' => 'Hakediş',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense', $this->filters()))
            ->assertOk()
            ->assertSee('BAKIRKÖY', false)
            ->assertSee('Geçmiş Dönemler (Devir)', false)
            ->assertSee('Nakit', false)
            ->viewData('report');

        $this->assertSame(-1000.0, $report['opening']);
        $this->assertCount(2, $report['lines']);
        $this->assertSame(-700.0, $report['lines'][0]['balance']);
        $this->assertSame(-1200.0, $report['lines'][1]['balance']);
        $this->assertSame(300.0, $report['total_debit']);
        $this->assertSame(500.0, $report['total_credit']);
        $this->assertSame(-1200.0, $report['closing']);
    }

    public function test_soft_deleted_excluded(): void
    {
        $gone = SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'debit' => 200,
            'credit' => 0,
            'occurred_on' => '2026-09-05',
        ]);
        $gone->delete();

        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'debit' => 100,
            'credit' => 0,
            'occurred_on' => '2026-09-06',
        ]);

        $report = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense', $this->filters()))
            ->viewData('report');

        $this->assertCount(1, $report['lines']);
        $this->assertSame(100.0, $report['total_debit']);
    }

    public function test_csv_export(): void
    {
        SiteTransaction::query()->create([
            'site_id' => $this->site->id,
            'debit' => 75,
            'credit' => 0,
            'occurred_on' => '2026-09-05',
            'description' => 'Test',
        ]);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.reports.site-expense.export', $this->filters()))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Borç;Alacak;Bakiye', $csv);
        $this->assertStringContainsString('75,00', $csv);
    }

    /**
     * @return array{period: string, year: int, month: int, site: string}
     */
    private function filters(): array
    {
        return [
            'period' => 'month',
            'year' => 2026,
            'month' => 9,
            'site' => $this->site->id,
        ];
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.cari-entries.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_supplier_credit_is_posted_on_the_party_ledger(): void
    {
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.create'))
            ->assertOk()
            ->assertSee('Satıcı petrol');

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '1500',
                'occurred_on' => '2026-06-01',
                'payment_method' => 'cash',
                'description' => 'Malzeme',
            ])
            ->assertRedirect(route('admin.cari-entries.index'))
            ->assertSessionHas('success');

        $row = CompanyTransaction::query()->sole();
        $this->assertSame(PartyRole::Supplier, $row->role);
        $this->assertSame('1500.00', $row->credit);
        $this->assertSame('0.00', $row->debit);

        $entry = LedgerEntry::query()->sole();
        $this->assertSame('company_transaction', $entry->source_type);
        $this->assertSame($supplier->id, $entry->party_id);
        $this->assertSame('1500.00', $entry->credit);
        $this->assertSame(PaymentMethod::Cash, $entry->payment_method);

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index'))
            ->assertOk()
            ->assertSee('Malzeme')
            ->assertSee('Satıcı cari');
    }

    public function test_party_filter_returns_only_entries_for_the_selected_party(): void
    {
        $selected = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $other = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        foreach ([[$selected, 'Seçilen parti hareketi'], [$other, 'Diğer parti hareketi']] as [$party, $description]) {
            $this->actingAs($this->admin)
                ->post(route('admin.cari-entries.store'), [
                    'kind' => 'supplier',
                    'direction' => 'credit',
                    'party_id' => $party->id,
                    'amount' => '100',
                    'occurred_on' => '2026-06-01',
                    'description' => $description,
                ])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index', ['party' => $selected->id]))
            ->assertOk()
            ->assertSee('Seçilen parti hareketi')
            ->assertDontSee('Diğer parti hareketi');
    }

    public function test_party_filter_uses_the_party_uuid_for_vehicle_entries(): void
    {
        $selected = Party::factory()->create();
        $other = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($selected, '2026-01-01')->create(['plate' => '34 CR 10']);

        VehiclePayment::query()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $selected->id,
            'amount' => 100,
            'occurred_on' => '2026-06-01',
            'description' => 'Seçilen araç ödemesi',
        ]);
        VehicleReceivable::query()->create([
            'vehicle_id' => $vehicle->id,
            'party_id' => $other->id,
            'amount' => 100,
            'occurred_on' => '2026-06-01',
            'description' => 'Diğer araç alacağı',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index', ['party' => $selected->id]))
            ->assertOk()
            ->assertSee('Seçilen araç ödemesi')
            ->assertDontSee('Diğer araç alacağı');
    }

    public function test_vehicle_payment_debits_the_owner_and_delete_reverses_it(): void
    {
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 10']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 10',
                'amount' => '800',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cheque',
            ])
            ->assertSessionHasNoErrors();

        $payment = VehiclePayment::query()->sole();
        $this->assertSame($owner->id, $payment->party_id);
        $this->assertSame('800.00', LedgerEntry::query()->sole()->debit);

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertRedirect(route('admin.cari-entries.index'));

        $this->assertSoftDeleted($payment);
        $this->assertSame(0.0, (float) LedgerEntry::query()->where('party_id', $owner->id)->selectRaw('coalesce(sum(debit - credit), 0) as balance')->value('balance'));
    }

    public function test_cash_in_does_not_touch_a_party_ledger(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'cash',
                'direction' => 'debit',
                'amount' => '250',
                'occurred_on' => '2026-06-03',
                'description' => 'Bankadan çekilen',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('250.00', CashEntry::query()->sole()->debit);
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_cash_search_disables_party_and_drops_it_from_filters(): void
    {
        $party = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        CashEntry::query()->create([
            'debit' => 100,
            'credit' => 0,
            'occurred_on' => '2026-06-03',
            'description' => 'Kasa nakit hareketi',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index', [
                'kind' => 'cash',
                'party' => $party->id,
            ]))
            ->assertOk()
            ->assertSee('Kasa nakit hareketi')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<select\b[^>]*\bname="party"[^>]*\bdisabled\b|<\s*select\b[^>]*\bdisabled\b[^>]*\bname="party"/i',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option[^>]*value="'.$party->id.'"[^>]*selected/i',
            $html,
        );
    }

    public function test_party_lookup_returns_the_current_balance(): void
    {
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'Satıcı petrol']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '1500',
                'occurred_on' => '2026-06-01',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->getJson(route('admin.cari-entries.lookups.party', $supplier))
            ->assertOk()
            ->assertJsonPath('name', 'Satıcı petrol')
            ->assertJsonPath('debit', 0)
            ->assertJsonPath('credit', 1500)
            ->assertJsonPath('balance', -1500);

        $empty = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.cari-entries.lookups.party', $empty))
            ->assertOk()
            ->assertJsonPath('balance', 0);
    }

    public function test_plate_lookup_returns_the_owners_balance_on_that_date(): void
    {
        $owner = Party::factory()->create(['name' => 'Araç sahibi']);
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 10']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 10',
                'amount' => '800',
                'occurred_on' => '2026-06-02',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->getJson(route('admin.cari-entries.lookups.plate', ['plate' => '34cr 10', 'date' => '2026-06-02']))
            ->assertOk()
            ->assertJsonPath('plate', '34 CR 10')
            ->assertJsonPath('name', 'Araç sahibi')
            ->assertJsonPath('balance', 800);

        $this->actingAs($this->admin)
            ->getJson(route('admin.cari-entries.lookups.plate', ['plate' => '34 YOK', 'date' => '2026-06-02']))
            ->assertNotFound()
            ->assertJsonPath('message', 'Kayıtlı bir plaka girin.');

        $this->actingAs($this->admin)
            ->getJson(route('admin.cari-entries.lookups.plate', ['plate' => '34 CR 10', 'date' => '2025-01-01']))
            ->assertNotFound()
            ->assertJsonPath('message', 'Bu tarihte aracın kayıtlı sahibi yok.');
    }

    public function test_supplier_entry_rejects_a_party_without_the_role(): void
    {
        $party = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'debit',
                'party_id' => $party->id,
                'amount' => '10',
                'occurred_on' => '2026-06-04',
            ])
            ->assertSessionHasErrors('party_id');
    }
}

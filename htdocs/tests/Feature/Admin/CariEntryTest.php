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

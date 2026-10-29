<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\PartyRole;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\Employee;
use App\Models\FinancialInstrument;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\Site;
use App\Models\SiteBill;
use App\Models\SiteTransaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Services\PartyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Attempts to move money onto a cari it cannot be traced back from, or to let the cheque
 * portfolio and the cari tell different stories.
 */
class CariLeakTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $temporary;

    private Party $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-29 12:00:00');
        $this->admin = User::factory()->yonetici()->create();
        $this->temporary = app(PartyService::class)->temporaryParty();
        $this->temporary->assignRole(PartyRole::VehicleOwner);
        $this->customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
    }

    public function test_temporary_cari_takes_no_money_that_is_not_tied_to_a_plate(): void
    {
        $other = Party::factory()->create();
        $base = ['amount' => '1000', 'occurred_on' => '2026-06-01'];

        foreach ([
            ['kind' => 'owner_payment', 'party_id' => $this->temporary->id, 'payment_method' => 'offset'],
            ['kind' => 'opening', 'direction' => 'debit', 'party_id' => $this->temporary->id],
            ['kind' => 'party_transfer', 'party_id' => $this->temporary->id, 'counter_party_id' => $other->id],
            ['kind' => 'party_transfer', 'party_id' => $other->id, 'counter_party_id' => $this->temporary->id],
        ] as $attempt) {
            $this->actingAs($this->admin)
                ->post(route('admin.cari-entries.store'), [...$base, ...$attempt])
                ->assertSessionHasErrors();
        }

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.store'), [
                ...$base,
                'direction' => 'given',
                'payment_method' => 'cheque',
                'counterparty' => 'vehicle_owner|'.$this->temporary->id,
                'due_on' => '2026-07-01',
            ])
            ->assertSessionHasErrors('counterparty');

        $this->receiveCheque();

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', FinancialInstrument::query()->sole()), [
                'type' => 'endorse',
                'occurred_on' => '2026-06-05',
                'party_id' => $this->temporary->id,
            ])
            ->assertSessionHasErrors('party_id');

        $this->assertSame(0, LedgerEntry::query()->where('party_id', $this->temporary->id)->count());
    }

    public function test_cheque_paid_on_a_temporary_plate_follows_the_plate_to_its_real_owner(): void
    {
        $plate = Vehicle::factory()->ownedBy($this->temporary, '2026-03-01')->create(['plate' => '06 YB 123']);
        $payment = [
            'kind' => 'vehicle_payment',
            'plate' => '06 YB 123',
            'occurred_on' => '2026-03-10',
            'payment_method' => 'cheque',
            'due_on' => '2026-04-10',
            'receiver_name' => 'Ali Veli',
            'receiver_national_id' => '12345678901',
        ];

        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), [...$payment, 'amount' => '1000', 'cheque_no' => 'A1'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), [...$payment, 'amount' => '400', 'cheque_no' => 'A2'])->assertSessionHasNoErrors();

        $bounced = FinancialInstrument::query()->where('number', 'A1')->sole();
        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $bounced), ['type' => 'bounce', 'occurred_on' => '2026-04-11'])
            ->assertSessionHas('success');

        $market = Party::factory()->company()->withRole(PartyRole::Market)->create();
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), ['kind' => 'market', 'direction' => 'debit', 'party_id' => $market->id, 'plate' => '06 YB 123', 'amount' => '300', 'occurred_on' => '2026-03-12'])
            ->assertSessionHasNoErrors();

        $owner = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.reassign', $plate), ['party_id' => $owner->id])
            ->assertSessionHas('success');

        $this->assertSame($owner->id, CompanyTransaction::query()->where('role', 'market')->sole()->owner_party_id);
        $this->assertSame([$owner->id], FinancialInstrument::query()->pluck('party_id')->unique()->values()->all());
        $this->assertSame(0.0, $this->balance($this->temporary));
        $this->assertSame(400.0, $this->balance($owner));

        $pending = FinancialInstrument::query()->where('number', 'A2')->sole();
        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $pending), ['type' => 'bounce', 'occurred_on' => '2026-04-12'])
            ->assertSessionHas('success');

        $this->assertSame(0.0, $this->balance($owner));
        $this->assertSame(0.0, $this->balance($this->temporary));

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_moved_cheque_keeps_its_cari_and_its_date_order(): void
    {
        $other = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();
        $entry = CompanyTransaction::query()->sole();

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $cheque), ['type' => 'deposit', 'occurred_on' => '2026-06-05', 'bank_id' => Bank::factory()->create(['type' => AccountType::Bank])->id])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'customer', 'entry' => $entry->id]), [...$this->chequePayload(), 'party_id' => $other->id])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'customer', 'entry' => $entry->id]), [...$this->chequePayload(), 'occurred_on' => '2026-06-06', 'due_on' => '2026-08-01'])
            ->assertSessionHas('error');

        $this->assertSame($this->customer->id, $entry->refresh()->party_id);
        $this->assertSame('2026-06-01', $entry->occurred_on->toDateString());
        $this->assertSame(-10000.0, $this->balance($this->customer));
        $this->assertSame(0.0, $this->balance($other));
    }

    public function test_editing_an_old_site_record_keeps_it_on_the_cari_it_was_posted_to(): void
    {
        $first = Party::factory()->company()->create();
        $second = Party::factory()->company()->create();
        $site = Site::factory()->create(['party_id' => $first->id]);
        $cashBox = Bank::factory()->create(['type' => AccountType::Cash]);
        $bill = ['kind' => 'site_bill', 'site_id' => $site->id, 'amount' => '5000', 'occurred_on' => '2026-06-01'];
        $collection = ['kind' => 'site', 'direction' => 'credit', 'site_id' => $site->id, 'amount' => '2000', 'occurred_on' => '2026-06-02', 'payment_method' => 'cash', 'bank_id' => $cashBox->id];

        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), $bill)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), $collection)->assertSessionHasNoErrors();

        $site->update(['party_id' => $second->id]);

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'site_bill', 'entry' => SiteBill::query()->sole()->id]), [...$bill, 'description' => 'Haziran hakedişi'])
            ->assertSessionHas('success');
        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'site', 'entry' => SiteTransaction::query()->sole()->id]), [...$collection, 'description' => 'Tahsilat'])
            ->assertSessionHas('success');

        $this->assertSame(3000.0, $this->balance($first));
        $this->assertSame(0.0, $this->balance($second));
    }

    public function test_transfer_cannot_be_undone_while_the_new_owner_holds_plate_payments(): void
    {
        $first = Party::factory()->create();
        $second = Party::factory()->create();
        $plate = Vehicle::factory()->ownedBy($first, '2026-01-01')->create(['plate' => '34 UN 100']);
        $cashBox = Bank::factory()->create(['type' => AccountType::Cash]);

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $plate), ['party_id' => $second->id, 'started_on' => '2026-05-01', 'settlement' => 'carry'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), ['kind' => 'vehicle_payment', 'plate' => '34 UN 100', 'amount' => '3500', 'occurred_on' => '2026-05-10', 'payment_method' => 'cash', 'bank_id' => $cashBox->id])
            ->assertSessionHasNoErrors();

        $transfer = $plate->currentOwnership()->first();

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.ownerships.destroy', [$plate, $transfer]))
            ->assertSessionHas('error');

        $this->assertNotNull($transfer->fresh());

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'vehicle_payment', 'entry' => VehiclePayment::query()->sole()->id]))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->delete(route('admin.vehicles.ownerships.destroy', [$plate, $transfer]))
            ->assertSessionHas('success');

        $this->assertSame(0.0, $this->balance($second));
    }

    public function test_cheque_is_refused_wherever_it_would_not_enter_the_portfolio(): void
    {
        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 RC 100']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), ['kind' => 'vehicle_receivable', 'plate' => '34 RC 100', 'amount' => '500', 'occurred_on' => '2026-06-01', 'payment_method' => 'cheque', 'due_on' => '2026-07-01'])
            ->assertSessionHasErrors('payment_method');

        $this->actingAs($this->admin)
            ->post(route('admin.employee-payments.store'), ['employee_id' => Employee::factory()->create()->id, 'amount' => '500', 'payment_method' => 'promissory_note', 'year' => 2026, 'month' => 6, 'occurred_on' => '2026-06-30'])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_account_holding_collected_money_cannot_be_deleted(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank, 'opening_balance' => 0]);
        $this->receiveCheque();

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', FinancialInstrument::query()->sole()), ['type' => 'collect', 'occurred_on' => '2026-08-01', 'bank_id' => $bank->id])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->delete(route('admin.banks.destroy', $bank))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($bank);
    }

    public function test_audit_catches_a_portfolio_record_that_drifted_from_its_cari_entry(): void
    {
        $this->receiveCheque();

        $this->artisan('ledger:audit')->assertSuccessful();

        DB::table('financial_instruments')->update(['amount' => 9000]);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Çek / senet portföyü cari hareketleriyle aynı · kontrol 1 · uyumsuz 1')
            ->assertFailed();
    }

    public function test_cheque_screen_needs_the_right_to_write_cari_entries(): void
    {
        (new RolePermissionSeeder)->run();
        $clerk = User::factory()->create();
        $clerk->givePermissionTo(['view-financial-instrument', 'create-financial-instrument', 'view-dashboard']);

        $this->actingAs($clerk)
            ->post(route('admin.financial-instruments.store'), [
                'direction' => 'received',
                'payment_method' => 'cheque',
                'counterparty' => 'customer|'.$this->customer->id,
                'amount' => '1000',
                'occurred_on' => '2026-06-01',
                'due_on' => '2026-07-01',
            ])
            ->assertForbidden();

        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(0, VehiclePayment::query()->count());

        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();
        $clerk->givePermissionTo('edit-financial-instrument');

        foreach (['endorse' => ['party_id' => $this->customer->id], 'bounce' => []] as $type => $extra) {
            $this->actingAs($clerk)
                ->post(route('admin.financial-instruments.movements.store', $cheque), ['type' => $type, 'occurred_on' => '2026-06-05', ...$extra])
                ->assertForbidden();
        }

        $this->actingAs($clerk)
            ->post(route('admin.financial-instruments.movements.store', $cheque), ['type' => 'deposit', 'occurred_on' => '2026-06-05', 'bank_id' => Bank::factory()->create(['type' => AccountType::Bank])->id])
            ->assertSessionHas('success');
    }

    /**
     * @return array<string, string>
     */
    private function chequePayload(): array
    {
        return [
            'kind' => 'customer',
            'direction' => 'credit',
            'party_id' => $this->customer->id,
            'amount' => '10000',
            'occurred_on' => '2026-06-01',
            'payment_method' => 'cheque',
            'cheque_no' => 'CK-1',
            'due_on' => '2026-08-01',
        ];
    }

    private function receiveCheque(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $this->chequePayload())
            ->assertSessionHasNoErrors();
    }

    private function balance(Party $party): float
    {
        return round((float) LedgerEntry::query()->where('party_id', $party->id)->sum(DB::raw('debit - credit')), 2);
    }
}

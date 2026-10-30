<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\InstrumentDirection;
use App\Enums\InstrumentStatus;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\FinancialInstrument;
use App\Models\FinancialInstrumentMovement;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Repositories\BankRepository;
use App\Services\CariEntryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FinancialInstrumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $customer;

    private Party $supplier;

    private Bank $bank;

    private Bank $cashBox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-29 12:00:00');
        $this->admin = User::factory()->yonetici()->create();
        $this->customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $this->supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $this->bank = Bank::factory()->create(['type' => AccountType::Bank, 'opening_balance' => 1000]);
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash, 'opening_balance' => 0]);
    }

    public function test_cheque_taken_on_a_cari_entry_settles_the_cari_and_waits_in_the_portfolio(): void
    {
        $this->receiveCheque()->assertSessionHasNoErrors();

        $cheque = FinancialInstrument::query()->sole();
        $this->assertSame(InstrumentDirection::Received, $cheque->direction);
        $this->assertSame(InstrumentStatus::Portfolio, $cheque->status);
        $this->assertSame(PaymentMethod::Cheque, $cheque->payment_method);
        $this->assertSame($this->customer->id, $cheque->party_id);
        $this->assertSame('10000.00', $cheque->amount);
        $this->assertSame('2026-08-01', $cheque->due_on->toDateString());
        $this->assertSame(CompanyTransaction::query()->sole()->id, $cheque->source_id);

        $this->assertSame(-10000.0, $this->balance($this->customer));
        $this->assertSame(1000.0, $this->accountBalance($this->bank));

        $this->actingAs($this->admin)
            ->get(route('admin.financial-instruments.index', ['status' => 'portfolio']))
            ->assertOk()
            ->assertSee('CK-1001')
            ->assertSee('Portföyde');

        $this->actingAs($this->admin)
            ->get(route('admin.financial-instruments.show', $cheque))
            ->assertOk()
            ->assertSee('Tahsil et')
            ->assertSee('Ciro et');
    }

    public function test_cheque_entered_on_its_own_screen_writes_the_cari_entry_of_the_chosen_group(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.financial-instruments.create'))
            ->assertOk()
            ->assertSee($this->customer->name);

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.store'), [
                'direction' => 'received',
                'payment_method' => 'cheque',
                'counterparty' => 'customer|'.$this->customer->id,
                'amount' => '7500',
                'occurred_on' => '2026-06-01',
                'due_on' => '2026-07-15',
                'cheque_no' => 'CK-55',
                'instrument_bank' => 'Garanti',
            ])
            ->assertRedirect(route('admin.financial-instruments.index'))
            ->assertSessionHas('success');

        $entry = CompanyTransaction::query()->sole();
        $this->assertSame(PartyRole::Customer, $entry->role);
        $this->assertSame('7500.00', $entry->credit);
        $this->assertSame(PaymentMethod::Cheque, $entry->payment_method);

        $cheque = FinancialInstrument::query()->sole();
        $this->assertSame($entry->id, $cheque->source_id);
        $this->assertSame(InstrumentStatus::Portfolio, $cheque->status);
        $this->assertSame('CK-55', $cheque->number);
        $this->assertSame('Garanti', $cheque->bank_name);
        $this->assertSame(-7500.0, $this->balance($this->customer));
    }

    public function test_note_given_to_a_vehicle_owner_is_an_owner_payment(): void
    {
        $owner = Party::factory()->withRole(PartyRole::VehicleOwner)->create();
        $payload = [
            'payment_method' => 'promissory_note',
            'counterparty' => 'vehicle_owner|'.$owner->id,
            'amount' => '3000',
            'occurred_on' => '2026-06-01',
            'due_on' => '2026-09-01',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.store'), [...$payload, 'direction' => 'received'])
            ->assertSessionHasErrors('counterparty');

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.store'), [...$payload, 'direction' => 'given'])
            ->assertSessionHasNoErrors();

        $note = FinancialInstrument::query()->sole();
        $this->assertSame('party_payment', $note->source_type);
        $this->assertSame(InstrumentStatus::Issued, $note->status);
        $this->assertSame(3000.0, $this->balance($owner));
    }

    public function test_entry_written_by_code_takes_the_paper_direction_from_its_kind(): void
    {
        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 SN 10']);

        app(CariEntryService::class)->create([
            'kind' => 'vehicle_payment',
            'plate' => '34 SN 10',
            'amount' => '6000',
            'occurred_on' => '2026-07-01',
            'payment_method' => PaymentMethod::PromissoryNote->value,
            'due_on' => '2026-09-01',
        ], null);

        $note = FinancialInstrument::query()->sole();
        $this->assertSame(InstrumentDirection::Given, $note->direction);
        $this->assertSame(InstrumentStatus::Issued, $note->status);
        $this->assertSame($owner->id, $note->party_id);
    }

    public function test_cari_must_hold_the_role_of_the_group_it_is_picked_from(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.store'), [
                'direction' => 'received',
                'payment_method' => 'cheque',
                'counterparty' => 'supplier|'.$this->customer->id,
                'amount' => '1000',
                'occurred_on' => '2026-06-01',
                'due_on' => '2026-06-01',
            ])
            ->assertSessionHasErrors(['counterparty' => 'Cari, "Tedarikçi" rolüne sahip olmalı.']);

        $this->assertSame(0, FinancialInstrument::query()->count());
        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_cari_entry_paid_by_cheque_needs_a_due_date_not_before_the_entry(): void
    {
        $this->receiveCheque(['due_on' => ''])
            ->assertSessionHasErrors(['due_on' => 'vade zorunludur.']);

        $this->receiveCheque(['due_on' => '2026-05-01'])
            ->assertSessionHasErrors('due_on');

        $this->receiveCheque(['payment_method' => 'offset', 'due_on' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, FinancialInstrument::query()->count());
    }

    public function test_photos_uploaded_with_the_cheque_are_kept_and_served_only_to_portfolio_users(): void
    {
        Storage::fake('data');

        $this->receiveCheque(['photos' => [UploadedFile::fake()->image('on.jpg', 2400, 1100), UploadedFile::fake()->image('arka.png', 800, 400)]])
            ->assertSessionHasNoErrors();

        $cheque = FinancialInstrument::query()->sole();
        $photos = $cheque->photos()->get();
        $this->assertCount(2, $photos);
        Storage::disk('data')->assertExists($photos[0]->path);
        $this->assertStringStartsWith('instrument-photos/', $photos[0]->path);
        $this->assertSame(1600, getimagesize(Storage::disk('data')->path($photos[0]->path))[0]);

        $this->actingAs($this->admin)
            ->get(route('admin.financial-instruments.show', $cheque))
            ->assertSee(route('admin.financial-instruments.photos.show', [$cheque, $photos[0]]));

        $this->actingAs($this->admin)
            ->get(route('admin.financial-instruments.photos.show', [$cheque, $photos[0]]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.financial-instruments.photos.show', [$cheque, $photos[0]]))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.photos.store', $cheque), ['photos' => [UploadedFile::fake()->create('cek.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('photos.0');

        $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.photos.store', $cheque), ['photos' => [UploadedFile::fake()->image('ciro.jpg')]])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->delete(route('admin.financial-instruments.photos.destroy', [$cheque, $photos[1]]))
            ->assertSessionHas('success');

        $this->assertSame(2, $cheque->photos()->count());
    }

    public function test_collecting_moves_the_money_into_the_account_and_undo_takes_it_back(): void
    {
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();

        $this->step($cheque, ['type' => 'collect', 'occurred_on' => '2026-08-01', 'bank_id' => $this->bank->id])
            ->assertRedirect(route('admin.financial-instruments.show', $cheque))
            ->assertSessionHas('success');

        $this->assertSame(InstrumentStatus::Collected, $cheque->refresh()->status);
        $this->assertSame(11000.0, $this->accountBalance($this->bank));
        $this->assertSame(-10000.0, $this->balance($this->customer));

        $this->step($cheque, ['type' => 'collect', 'occurred_on' => '2026-08-02', 'bank_id' => $this->bank->id])
            ->assertSessionHas('error', 'Tahsil edildi durumundaki bir çek için "Tahsil et" yapılamaz.');

        $this->actingAs($this->admin)
            ->delete(route('admin.financial-instruments.movements.undo', $cheque))
            ->assertSessionHas('success');

        $this->assertSame(InstrumentStatus::Portfolio, $cheque->refresh()->status);
        $this->assertSame(1000.0, $this->accountBalance($this->bank));
        $this->assertSoftDeleted(FinancialInstrumentMovement::withTrashed()->sole());
    }

    public function test_a_step_cannot_be_dated_before_the_cheque_came_in(): void
    {
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();

        $this->step($cheque, ['type' => 'collect', 'occurred_on' => '2026-05-31', 'bank_id' => $this->bank->id])
            ->assertSessionHas('error', 'İşlem tarihi 01.06.2026 tarihinden önce olamaz.');

        $this->assertSame(InstrumentStatus::Portfolio, $cheque->refresh()->status);
    }

    public function test_cheque_collected_into_a_cash_box_shows_in_the_cash_report_and_the_audit_holds(): void
    {
        $this->receiveCheque();

        $this->step(FinancialInstrument::query()->sole(), ['type' => 'collect', 'occurred_on' => '2026-08-01', 'bank_id' => $this->cashBox->id])
            ->assertSessionHas('success');

        $this->assertSame(10000.0, $this->accountBalance($this->cashBox));

        $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements', ['period' => 'month', 'year' => 2026, 'month' => 8]))
            ->assertOk()
            ->assertSee($this->customer->name.' Tahsilat');

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_endorsed_cheque_debits_the_endorsee_and_a_return_restores_both_caris(): void
    {
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();

        $this->step($cheque, ['type' => 'endorse', 'occurred_on' => '2026-06-10', 'party_id' => $this->supplier->id])
            ->assertSessionHas('success');

        $this->assertSame(InstrumentStatus::Endorsed, $cheque->refresh()->status);
        $this->assertSame(10000.0, $this->balance($this->supplier));
        $this->assertSame(PaymentMethod::Cheque, LedgerEntry::query()->where('party_id', $this->supplier->id)->sole()->payment_method);

        $this->step($cheque, ['type' => 'bounce', 'occurred_on' => '2026-08-02'])
            ->assertSessionHas('success');

        $this->assertSame(InstrumentStatus::Bounced, $cheque->refresh()->status);
        $this->assertSame(0.0, $this->balance($this->customer));
        $this->assertSame(0.0, $this->balance($this->supplier));
        $this->assertSame(1000.0, $this->accountBalance($this->bank));

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_given_note_is_paid_from_the_account_and_a_return_owes_the_party_again(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'debit',
                'party_id' => $this->supplier->id,
                'amount' => '5000',
                'occurred_on' => '2026-06-01',
                'payment_method' => 'promissory_note',
                'cheque_no' => 'SN-7',
                'due_on' => '2026-09-01',
            ])
            ->assertSessionHasNoErrors();

        $note = FinancialInstrument::query()->sole();
        $this->assertSame(InstrumentDirection::Given, $note->direction);
        $this->assertSame(InstrumentStatus::Issued, $note->status);
        $this->assertSame(5000.0, $this->balance($this->supplier));

        $this->step($note, ['type' => 'pay', 'occurred_on' => '2026-09-01', 'bank_id' => $this->bank->id])
            ->assertSessionHas('success');

        $this->assertSame(InstrumentStatus::Paid, $note->refresh()->status);
        $this->assertSame(-4000.0, $this->accountBalance($this->bank));
        $this->assertSame(5000.0, $this->balance($this->supplier));

        $this->actingAs($this->admin)->delete(route('admin.financial-instruments.movements.undo', $note));

        $this->step($note, ['type' => 'bounce', 'occurred_on' => '2026-09-02'])
            ->assertSessionHas('success');

        $this->assertSame(InstrumentStatus::Bounced, $note->refresh()->status);
        $this->assertSame(0.0, $this->balance($this->supplier));
        $this->assertSame(1000.0, $this->accountBalance($this->bank));
    }

    public function test_each_step_asks_for_what_it_needs(): void
    {
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();

        $this->step($cheque, ['type' => 'deposit', 'occurred_on' => '2026-06-05', 'bank_id' => $this->cashBox->id])
            ->assertSessionHasErrors(['bank_id' => 'Bu ödeme tipi için banka hesabı seçin.']);

        $this->step($cheque, ['type' => 'collect', 'occurred_on' => '2026-06-05'])
            ->assertSessionHasErrors(['bank_id' => 'Paranın geçtiği hesabı seçin.']);

        $this->step($cheque, ['type' => 'endorse', 'occurred_on' => '2026-06-05'])
            ->assertSessionHasErrors(['party_id' => 'Çekin verileceği cariyi seçin.']);

        $this->step($cheque, ['type' => 'pay', 'occurred_on' => '2026-06-05', 'bank_id' => $this->bank->id])
            ->assertSessionHas('error', 'Portföyde durumundaki bir çek için "Ödendi" yapılamaz.');

        $this->assertSame(0, FinancialInstrumentMovement::query()->count());
    }

    public function test_cari_entry_of_a_moved_cheque_keeps_its_amount_and_cannot_be_deleted(): void
    {
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();
        $entry = CompanyTransaction::query()->sole();

        $this->step($cheque, ['type' => 'deposit', 'occurred_on' => '2026-06-05', 'bank_id' => $this->bank->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.edit', ['kind' => 'customer', 'entry' => $entry->id]))
            ->assertOk()
            ->assertSee('Portföy durumu')
            ->assertSee('2026-08-01');

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.show', ['kind' => 'customer', 'entry' => $entry->id]))
            ->assertOk()
            ->assertSee('Tahsilde');

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'customer', 'entry' => $entry->id]), [...$this->chequePayload(), 'amount' => '9000'])
            ->assertSessionHas('error', 'Bu hareketin çek kaydı "Tahsilde" durumunda; önce Çek / Senet ekranından işlemlerini geri alın.');

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'customer', 'entry' => $entry->id]))
            ->assertSessionHas('error');

        $this->assertSame('10000.00', $entry->refresh()->credit);
        $this->assertNotSoftDeleted($entry);

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'customer', 'entry' => $entry->id]), [...$this->chequePayload(), 'due_on' => '2026-09-15'])
            ->assertSessionHas('success');

        $this->assertSame('2026-09-15', $cheque->refresh()->due_on->toDateString());
        $this->assertSame(InstrumentStatus::InCollection, $cheque->status);
    }

    public function test_switching_an_untouched_entry_away_from_cheque_drops_it_from_the_portfolio(): void
    {
        $this->receiveCheque();
        $entry = CompanyTransaction::query()->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'customer', 'entry' => $entry->id]), [
                ...$this->chequePayload(),
                'payment_method' => 'bank_transfer',
                'bank_id' => $this->bank->id,
            ])
            ->assertSessionHas('success');

        $this->assertSoftDeleted(FinancialInstrument::withTrashed()->sole());
        $this->assertSame(0, FinancialInstrument::query()->count());
    }

    public function test_view_only_user_sees_the_portfolio_but_cannot_record_or_undo_steps(): void
    {
        $this->receiveCheque();
        $cheque = FinancialInstrument::query()->sole();
        (new RolePermissionSeeder)->run();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-financial-instrument', 'view-dashboard']);

        $this->actingAs($viewer)
            ->get(route('admin.financial-instruments.show', $cheque))
            ->assertOk()
            ->assertDontSee('Tahsil et');

        $this->actingAs($viewer)
            ->post(route('admin.financial-instruments.movements.store', $cheque), ['type' => 'collect', 'occurred_on' => '2026-08-01', 'bank_id' => $this->bank->id])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('admin.financial-instruments.movements.undo', $cheque))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.financial-instruments.create'))
            ->assertForbidden();
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
            'cheque_no' => 'CK-1001',
            'due_on' => '2026-08-01',
            'instrument_bank' => 'Ziraat Bankası',
            'instrument_drawer' => 'Ali Yılmaz',
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function receiveCheque(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$this->chequePayload(), ...$overrides]);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function step(FinancialInstrument $instrument, array $data): TestResponse
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.financial-instruments.movements.store', $instrument), $data);
    }

    private function balance(Party $party): float
    {
        return round((float) LedgerEntry::query()->where('party_id', $party->id)->sum(DB::raw('debit - credit')), 2);
    }

    private function accountBalance(Bank $account): float
    {
        return app(BankRepository::class)->balances([$account->id])[$account->id];
    }
}

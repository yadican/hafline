<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\CariEntryGroup;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\AccountTransfer;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\CashEntry;
use App\Models\CompanyTransaction;
use App\Models\LedgerEntry;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\PartyTransfer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Models\VehicleReceivable;
use App\Repositories\BankRepository;
use App\Repositories\PartyRepository;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CariEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Bank $cashBox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash, 'name' => 'Merkez Kasa']);
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
            ->assertSee('Satıcı Petrol');

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '1500',
                'occurred_on' => '2026-06-01',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
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
            ->assertSee('Satıcı Cari');

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.show', ['kind' => 'supplier', 'entry' => $row->id]))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee('Kasa girişi (alacak)')
            ->assertSee('1.500,00 TL')
            ->assertSee('Malzeme');
    }

    public function test_show_page_lists_each_change_of_the_cari_and_amount(): void
    {
        $this->travelTo('2026-10-30 09:15:00');
        $first = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'ESKİ SATICI']);
        $second = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'YENİ SATICI']);
        $payload = [
            'kind' => 'supplier',
            'direction' => 'credit',
            'party_id' => $first->id,
            'amount' => '1000',
            'occurred_on' => '2026-10-01',
            'payment_method' => 'cash',
            'bank_id' => $this->cashBox->id,
        ];
        $this->actingAs($this->admin)->post(route('admin.cari-entries.store'), $payload)->assertSessionHasNoErrors();
        $show = route('admin.cari-entries.show', ['kind' => 'supplier', 'entry' => CompanyTransaction::query()->sole()->id]);
        $update = route('admin.cari-entries.update', ['kind' => 'supplier', 'entry' => CompanyTransaction::query()->sole()->id]);

        $this->actingAs($this->admin)->get($show)
            ->assertOk()
            ->assertSee('Bu hareket kaydedildiğinden beri cari, tutar veya yönü değiştirilmedi.');

        $this->actingAs($this->admin)->put($update, [...$payload, 'party_id' => $second->id])->assertSessionHasNoErrors();
        $this->travelTo('2026-10-30 10:40:00');
        $this->actingAs($this->admin)->put($update, [...$payload, 'party_id' => $second->id, 'amount' => '1200'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put($update, [...$payload, 'party_id' => $second->id, 'amount' => '1200', 'description' => 'Not'])->assertSessionHasNoErrors();

        $response = $this->actingAs($this->admin)->get($show)->assertOk();

        $corrections = $response->viewData('details')['corrections'];
        $this->assertCount(2, $corrections);
        $this->assertSame('1000.00', $corrections[0]['before']->sole()->credit);
        $this->assertSame('1200.00', $corrections[0]['after']->sole()->credit);
        $this->assertSame($first->id, $corrections[1]['before']->sole()->party_id);
        $this->assertSame($second->id, $corrections[1]['after']->sole()->party_id);
        $this->assertSame($this->admin->id, $corrections[1]['user']->id);
        $response->assertSeeInOrder(['Düzeltme geçmişi', '30.10.2026 10:40', '30.10.2026 09:15', 'ESKİ SATICI', 'YENİ SATICI'])
            ->assertSee($this->admin->name);
    }

    public function test_view_permission_can_show_an_entry_but_cannot_edit(): void
    {
        (new RolePermissionSeeder)->run();

        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 77']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 77',
                'amount' => '750',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertSessionHas('success');

        $payment = VehiclePayment::query()->sole();
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-vehicle-payment-cari-entry', 'view-dashboard']);

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.show', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertOk()
            ->assertSee('34 CR 77')
            ->assertSee($owner->name)
            ->assertDontSee('Düzenle');

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.edit', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('admin.cari-entries.update', ['kind' => 'vehicle_payment', 'entry' => $payment->id]), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 77',
                'amount' => '800',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.show', ['kind' => 'vehicle_payment', 'entry' => '00000000-0000-0000-0000-000000000000']))
            ->assertNotFound();
    }

    public function test_edit_and_delete_respect_group_permissions(): void
    {
        (new RolePermissionSeeder)->run();

        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 35C']);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => '35C SATICI']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 35C',
                'amount' => '600',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'description' => '35c plaka ödemesi',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '700',
                'occurred_on' => '2026-06-03',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'description' => '35c firma tahsilatı',
            ])
            ->assertSessionHas('success');

        $payment = VehiclePayment::query()->sole();
        $firm = CompanyTransaction::query()->sole();

        $clerk = User::factory()->create();
        $clerk->givePermissionTo([
            'view-dashboard',
            'view-vehicle-payment-cari-entry',
            'view-firm-payment-cari-entry',
            'edit-vehicle-payment-cari-entry',
            'delete-vehicle-payment-cari-entry',
        ]);

        $indexHtml = $this->actingAs($clerk)
            ->get(route('admin.cari-entries.index'))
            ->assertOk()
            ->assertSee('35c plaka ödemesi')
            ->assertSee('35c firma tahsilatı')
            ->getContent();

        $this->assertStringContainsString(
            route('admin.cari-entries.edit', ['kind' => 'vehicle_payment', 'entry' => $payment->id], absolute: false),
            $indexHtml,
        );
        $this->assertMatchesRegularExpression(
            '#<form[^>]+action="[^"]*cari-entries/vehicle_payment/'.preg_quote($payment->id, '#').'"[^>]*>[\s\S]*?name="_method" value="DELETE"#',
            $indexHtml,
        );
        $this->assertStringNotContainsString(
            route('admin.cari-entries.edit', ['kind' => 'supplier', 'entry' => $firm->id], absolute: false),
            $indexHtml,
        );
        $this->assertDoesNotMatchRegularExpression(
            '#<form[^>]+action="[^"]*cari-entries/supplier/'.preg_quote($firm->id, '#').'"#',
            $indexHtml,
        );

        $this->actingAs($clerk)
            ->get(route('admin.cari-entries.show', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertOk()
            ->assertSee('Düzenle');

        $this->actingAs($clerk)
            ->get(route('admin.cari-entries.show', ['kind' => 'supplier', 'entry' => $firm->id]))
            ->assertOk()
            ->assertDontSee('Düzenle');

        $this->actingAs($clerk)
            ->get(route('admin.cari-entries.edit', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertOk();

        $this->actingAs($clerk)
            ->put(route('admin.cari-entries.update', ['kind' => 'vehicle_payment', 'entry' => $payment->id]), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 35C',
                'amount' => '650',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'description' => '35c plaka ödemesi güncel',
            ])
            ->assertRedirect(route('admin.cari-entries.index'))
            ->assertSessionHas('success');

        $this->assertSame('650.00', VehiclePayment::query()->sole()->amount);

        $this->actingAs($clerk)
            ->get(route('admin.cari-entries.edit', ['kind' => 'supplier', 'entry' => $firm->id]))
            ->assertForbidden();

        $this->actingAs($clerk)
            ->put(route('admin.cari-entries.update', ['kind' => 'supplier', 'entry' => $firm->id]), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '800',
                'occurred_on' => '2026-06-03',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertForbidden();

        $this->assertSame('700.00', CompanyTransaction::query()->sole()->credit);

        $this->actingAs($clerk)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'supplier', 'entry' => $firm->id]))
            ->assertForbidden();

        $this->assertSame(1, CompanyTransaction::query()->count());

        $this->actingAs($clerk)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertRedirect(route('admin.cari-entries.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, VehiclePayment::query()->count());
    }

    public function test_index_and_show_respect_group_view_permissions(): void
    {
        (new RolePermissionSeeder)->run();

        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 35B']);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => '35B SATICI']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 35B',
                'amount' => '400',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'description' => '35b plaka ödemesi',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '900',
                'occurred_on' => '2026-06-03',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'description' => '35b firma tahsilatı',
            ])
            ->assertSessionHas('success');

        $payment = VehiclePayment::query()->sole();
        $firm = CompanyTransaction::query()->sole();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo([
            'view-dashboard',
            'view-vehicle-payment-cari-entry',
        ]);

        $index = $this->actingAs($viewer)
            ->get(route('admin.cari-entries.index'))
            ->assertOk()
            ->assertSee('35b plaka ödemesi')
            ->assertDontSee('35b firma tahsilatı')
            ->assertDontSee('Satıcı Cari');

        $kinds = $index->viewData('kinds');
        $this->assertArrayHasKey('vehicle_payment', $kinds);
        $this->assertArrayNotHasKey('supplier', $kinds);
        $this->assertArrayNotHasKey('customer', $kinds);
        $this->assertArrayNotHasKey('fuel', $kinds);
        $this->assertArrayNotHasKey('market', $kinds);

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.show', ['kind' => 'vehicle_payment', 'entry' => $payment->id]))
            ->assertOk()
            ->assertSee('34 CR 35B');

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.show', ['kind' => 'supplier', 'entry' => $firm->id]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.index', ['kind' => 'supplier']))
            ->assertRedirect(route('admin.cari-entries.index'));

        $denied = User::factory()->create();
        $denied->givePermissionTo(['view-dashboard']);

        $this->actingAs($denied)
            ->get(route('admin.cari-entries.index'))
            ->assertForbidden();
    }

    public function test_cari_group_menu_links_respect_view_permissions_and_open_group_create(): void
    {
        (new RolePermissionSeeder)->run();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo([
            'view-dashboard',
            'view-vehicle-payment-cari-entry',
            'create-vehicle-payment-cari-entry',
            'view-cash-category',
        ]);

        $vehicleIndex = route('admin.cari-entries.index', ['group' => CariEntryGroup::VehiclePayment->value]);
        $firmIndex = route('admin.cari-entries.index', ['group' => CariEntryGroup::FirmPayment->value]);
        $vehicleCreate = route('admin.cari-entries.create', ['group' => CariEntryGroup::VehiclePayment->value]);

        $this->actingAs($viewer)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cari işlemleri')
            ->assertSee(e($vehicleIndex), false)
            ->assertSee('Plaka ödeme')
            ->assertDontSee(e($firmIndex), false)
            ->assertDontSee('Firma ödeme/tahsilat');

        $index = $this->actingAs($viewer)
            ->get($vehicleIndex)
            ->assertOk()
            ->assertSee('Plaka ödeme')
            ->assertSee('Yeni Cari')
            ->assertSee(e($vehicleCreate), false);

        $this->assertSame(
            ['vehicle_payment' => 'Plaka Ödeme'],
            $index->viewData('kinds'),
        );

        $create = $this->actingAs($viewer)
            ->get($vehicleCreate)
            ->assertOk();

        $this->assertSame(
            ['vehicle_payment' => 'Plaka Ödeme'],
            $create->viewData('options')['kinds'],
        );
        $this->assertSame('vehicle_payment', $create->viewData('form')['kind']);

        $this->actingAs($viewer)
            ->get(route('admin.cari-entries.create', ['group' => CariEntryGroup::FirmPayment->value]))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get($firmIndex)
            ->assertRedirect(route('admin.cari-entries.index'));
    }

    public function test_can_create_vehicle_payment_but_not_firm_payment(): void
    {
        (new RolePermissionSeeder)->run();

        $clerk = User::factory()->create();
        $clerk->givePermissionTo([
            'view-dashboard',
            'view-cari-entry',
            'create-vehicle-payment-cari-entry',
        ]);

        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 35']);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        $create = $this->actingAs($clerk)
            ->get(route('admin.cari-entries.create'))
            ->assertOk();

        $kinds = $create->viewData('options')['kinds'];
        $this->assertArrayHasKey('vehicle_payment', $kinds);
        $this->assertArrayNotHasKey('supplier', $kinds);
        $this->assertArrayNotHasKey('customer', $kinds);
        $this->assertArrayNotHasKey('fuel', $kinds);
        $this->assertArrayNotHasKey('market', $kinds);
        $create->assertSee('Plaka Ödeme')->assertDontSee('Satıcı Cari');

        $this->actingAs($clerk)
            ->get(route('admin.cari-entries.create', ['kind' => 'supplier']))
            ->assertOk()
            ->assertSee('Plaka Ödeme');

        $this->assertSame(
            'vehicle_payment',
            $this->actingAs($clerk)->get(route('admin.cari-entries.create', ['kind' => 'supplier']))->viewData('form')['kind']
        );

        $this->actingAs($clerk)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => '34 CR 35',
                'amount' => '500',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertRedirect(route('admin.cari-entries.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, VehiclePayment::query()->count());

        $this->actingAs($clerk)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '500',
                'occurred_on' => '2026-06-02',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertForbidden();

        $this->assertSame(0, CompanyTransaction::query()->count());
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
                    'payment_method' => 'cash',
                    'bank_id' => $this->cashBox->id,
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

    public function test_opening_balance_posts_once_per_party_without_a_payment_method(): void
    {
        $party = Party::factory()->create(['name' => 'Rolsüz Cari']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'opening',
                'direction' => 'credit',
                'party_id' => $party->id,
                'amount' => '12500',
                'occurred_on' => '2026-01-01',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'cash_category_id' => CashCategory::factory()->create()->id,
            ])
            ->assertSessionHasNoErrors();

        $opening = OpeningBalance::query()->sole();
        $entry = LedgerEntry::query()->sole();
        $this->assertSame('opening_balance', $entry->source_type);
        $this->assertSame($party->id, $entry->party_id);
        $this->assertSame('12500.00', $entry->credit);
        $this->assertNull($entry->payment_method);
        $this->assertNull($entry->cash_category_id);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'opening',
                'direction' => 'debit',
                'party_id' => $party->id,
                'amount' => '100',
                'occurred_on' => '2026-01-01',
            ])
            ->assertSessionHasErrors(['party_id' => 'Bu carinin açılış bakiyesi zaten girilmiş; mevcut kaydı düzenleyin.']);

        $this->actingAs($this->admin)
            ->put(route('admin.cari-entries.update', ['kind' => 'opening', 'entry' => $opening->id]), [
                'kind' => 'opening',
                'direction' => 'debit',
                'party_id' => $party->id,
                'amount' => '9000',
                'occurred_on' => '2026-01-01',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(9000.0, app(PartyRepository::class)->totals($party)['balance']);

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'opening', 'entry' => $opening->id]));

        $this->assertSame(0.0, app(PartyRepository::class)->totals($party)['balance']);
    }

    public function test_party_transfer_debits_one_party_and_credits_the_other(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create(['name' => 'ALICI A.Ş.']);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'SATICI A.Ş.']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'party_transfer',
                'party_id' => $supplier->id,
                'counter_party_id' => $customer->id,
                'amount' => '3000',
                'occurred_on' => '2026-06-05',
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
                'description' => 'Mahsup',
            ])
            ->assertSessionHasNoErrors();

        $transfer = PartyTransfer::query()->sole();
        $this->assertSame(3000.0, app(PartyRepository::class)->totals($supplier)['balance']);
        $this->assertSame(-3000.0, app(PartyRepository::class)->totals($customer)['balance']);
        $this->assertSame(0, LedgerEntry::query()->whereNotNull('payment_method')->count());

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index'))
            ->assertSee('SATICI A.Ş. → ALICI A.Ş.');

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.show', ['kind' => 'party_transfer', 'entry' => $transfer->id]))
            ->assertOk()
            ->assertSeeInOrder(['Borçlanan cari', 'SATICI A.Ş.', 'Alacaklanan cari', 'ALICI A.Ş.']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'party_transfer',
                'party_id' => $supplier->id,
                'counter_party_id' => $supplier->id,
                'amount' => '10',
                'occurred_on' => '2026-06-05',
            ])
            ->assertSessionHasErrors(['counter_party_id' => 'Virmanın iki tarafı farklı cari olmalı.']);

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'party_transfer', 'entry' => $transfer->id]));

        $this->assertSame(0.0, app(PartyRepository::class)->totals($supplier)['balance']);
        $this->assertSame(0.0, app(PartyRepository::class)->totals($customer)['balance']);
    }

    public function test_account_transfer_moves_money_between_our_accounts_without_touching_a_cari(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank, 'name' => 'Ziraat', 'opening_balance' => 10000]);
        $payload = ['kind' => 'account_transfer', 'amount' => '4000', 'occurred_on' => '2026-06-05', 'description' => 'Bankadan nakit çekim'];

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $payload)
            ->assertSessionHasErrors(['bank_id' => 'Paranın çıktığı hesabı seçin.', 'counter_bank_id' => 'Paranın girdiği hesabı seçin.']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payload, 'bank_id' => $bank->id, 'counter_bank_id' => $bank->id])
            ->assertSessionHasErrors(['counter_bank_id' => 'Virmanın iki tarafı farklı hesap olmalı.']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payload, 'bank_id' => $bank->id, 'counter_bank_id' => $this->cashBox->id, 'payment_method' => 'cash'])
            ->assertSessionHasNoErrors();

        $transfer = AccountTransfer::query()->sole();
        $this->assertSame(0, LedgerEntry::query()->count());
        $this->assertSame(
            [$bank->id => 6000.0, $this->cashBox->id => 4000.0],
            app(BankRepository::class)->balances([$bank->id, $this->cashBox->id]),
        );

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index'))
            ->assertSee('Ziraat → Merkez Kasa');

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.show', ['kind' => 'account_transfer', 'entry' => $transfer->id]))
            ->assertOk()
            ->assertSeeInOrder(['Çıkan hesap', 'Ziraat', 'Giren hesap', 'Merkez Kasa']);

        $cash = $this->actingAs($this->admin)
            ->get(route('admin.reports.cash-movements', ['period' => 'month', 'year' => 2026, 'month' => 6]))
            ->assertSee('Hesaplar Arası Virman')
            ->viewData('report');
        $this->assertSame(4000.0, $cash['closing']);

        $this->artisan('ledger:audit', ['--skip-reports' => true])
            ->expectsOutputToContain('Kasa raporu = kasa hesaplarının bakiyesi · kontrol 1 · uyumsuz 0')
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->delete(route('admin.cari-entries.destroy', ['kind' => 'account_transfer', 'entry' => $transfer->id]));

        $this->assertSoftDeleted($transfer);
        $this->assertSame(10000.0, app(BankRepository::class)->balances([$bank->id])[$bank->id]);
    }

    public function test_account_transfer_is_reached_from_the_account_screens(): void
    {
        $bank = Bank::factory()->create(['type' => AccountType::Bank, 'name' => 'Ziraat']);
        $createUrl = route('admin.cari-entries.create', ['kind' => 'account_transfer']);

        $this->actingAs($this->admin)
            ->get(route('admin.banks.index'))
            ->assertSee('Hesaplar arası virman')
            ->assertSee(e($createUrl), false);

        $this->actingAs($this->admin)
            ->get(route('admin.banks.show', $bank))
            ->assertSee('Bu hesaptan virman')
            ->assertSee(e(route('admin.cari-entries.create', ['kind' => 'account_transfer', 'bank_id' => $bank->id])), false);

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.create', ['kind' => 'account_transfer', 'bank_id' => $bank->id]))
            ->assertOk()
            ->assertSee('Hesaplar arası virman')
            ->assertSee('Çıkan hesap')
            ->assertSee('value="'.$bank->id.'" selected', false);

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.index', ['kind' => 'account_transfer']))
            ->assertOk()
            ->assertSee('Yeni virman')
            ->assertSee(e($createUrl), false);
    }

    public function test_account_transfer_buttons_on_bank_screens_require_create_permission(): void
    {
        (new RolePermissionSeeder)->run();

        $bank = Bank::factory()->create(['type' => AccountType::Bank, 'name' => '37 Ziraat']);

        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['view-dashboard', 'view-bank']);

        $this->actingAs($viewer)
            ->get(route('admin.banks.index'))
            ->assertOk()
            ->assertDontSee('Hesaplar arası virman');

        $this->actingAs($viewer)
            ->get(route('admin.banks.show', $bank))
            ->assertOk()
            ->assertDontSee('Bu hesaptan virman');

        $clerk = User::factory()->create();
        $clerk->givePermissionTo([
            'view-dashboard',
            'view-bank',
            'create-account-transfer-cari-entry',
        ]);

        $this->actingAs($clerk)
            ->get(route('admin.banks.index'))
            ->assertOk()
            ->assertSee('Hesaplar arası virman');

        $this->actingAs($clerk)
            ->get(route('admin.banks.show', $bank))
            ->assertOk()
            ->assertSee('Bu hesaptan virman');
    }

    public function test_payment_names_an_account_of_its_method_and_moves_that_balance(): void
    {
        $customer = Party::factory()->company()->withRole(PartyRole::Customer)->create();
        $bank = Bank::factory()->create(['type' => AccountType::Bank, 'opening_balance' => 1000]);
        $payload = ['kind' => 'customer', 'direction' => 'credit', 'party_id' => $customer->id, 'amount' => '2500', 'occurred_on' => '2026-06-04'];

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payload, 'payment_method' => 'bank_transfer'])
            ->assertSessionHasErrors(['bank_id' => 'Paranın geçtiği hesabı seçin.']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payload, 'payment_method' => 'bank_transfer', 'bank_id' => $this->cashBox->id])
            ->assertSessionHasErrors(['bank_id' => 'Bu ödeme tipi için banka hesabı seçin.']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payload, 'payment_method' => 'bank_transfer', 'bank_id' => $bank->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($bank->id, LedgerEntry::query()->sole()->bank_id);
        $this->assertSame(3500.0, app(BankRepository::class)->balances([$bank->id])[$bank->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.banks.show', $bank))
            ->assertSee('3.500,00 TL');

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payload, 'payment_method' => 'cheque', 'bank_id' => $bank->id, 'due_on' => '2026-07-04'])
            ->assertSessionHasNoErrors();

        $this->assertNull(CompanyTransaction::query()->where('payment_method', 'cheque')->sole()->bank_id);
        $this->assertSame(3500.0, app(BankRepository::class)->balances([$bank->id])[$bank->id]);
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
                'due_on' => '2026-07-02',
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
                'bank_id' => $this->cashBox->id,
                'amount' => '250',
                'occurred_on' => '2026-06-03',
                'description' => 'Bankadan çekilen',
            ])
            ->assertSessionHasNoErrors();

        $cash = CashEntry::query()->sole();
        $this->assertSame('250.00', $cash->debit);
        $this->assertSame(0, LedgerEntry::query()->count());

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.show', ['kind' => 'cash', 'entry' => $cash->id]))
            ->assertOk()
            ->assertSee('Kasa girişi');
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
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
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
            ->assertJsonPath('balance', 800)
            ->assertJsonPath('plate_balance', 800);

        $other = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 CR 11']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'vehicle_payment',
                'plate' => $other->plate,
                'amount' => '300',
                'occurred_on' => '2026-06-03',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->getJson(route('admin.cari-entries.lookups.plate', ['plate' => '34 CR 10', 'date' => '2026-06-02']))
            ->assertJsonPath('balance', 1100)
            ->assertJsonPath('plate_balance', 800);

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
                'payment_method' => 'cash',
                'bank_id' => $this->cashBox->id,
            ])
            ->assertSessionHasErrors('party_id');
    }

    public function test_firm_entry_rejects_a_missing_payment_method(): void
    {
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.cari-entries.create', ['kind' => 'supplier']))
            ->assertOk()
            ->assertSee('Fatura için', false)
            ->assertSee(route('admin.invoices.index'), false)
            ->assertSee('Burada yalnız ödeme / tahsilat girilir', false);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'supplier',
                'direction' => 'credit',
                'party_id' => $supplier->id,
                'amount' => '1500',
                'occurred_on' => '2026-06-01',
            ])
            ->assertSessionHasErrors(['payment_method' => 'Fatura için Faturalar ekranını kullanın; burada ödeme tipi zorunludur.']);

        $this->assertSame(0, CompanyTransaction::query()->count());
    }
}

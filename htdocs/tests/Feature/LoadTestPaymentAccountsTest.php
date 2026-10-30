<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\CashDirection;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\CompanyTransaction;
use App\Models\DumpSite;
use App\Models\DumpSitePayment;
use App\Models\Party;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Services\CariEntryService;
use Database\Seeders\CashCategorySeeder;
use Database\Seeders\LoadTest\CatalogStage;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LoadTestPaymentAccountsTest extends TestCase
{
    use RefreshDatabase;

    private CatalogStage $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
        $this->catalog = new CatalogStage(new Progress(null));
        (new ReflectionMethod(CatalogStage::class, 'seedBanks'))->invoke($this->catalog, Carbon::parse('2026-01-01'));
    }

    public function test_bank_for_picks_account_matching_payment_method(): void
    {
        foreach ([PaymentMethod::Cash, PaymentMethod::BankTransfer, PaymentMethod::CreditCard] as $method) {
            $bank = $this->catalog->bankFor($method);
            $this->assertNotNull($bank, $method->label().' için hesap seçilmeli.');
            $this->assertContains(
                $bank->type,
                $method->accountTypes(),
                $method->label().' ödemesi '.$bank->type->label().' hesabından geçemez.',
            );
        }

        $this->assertNull($this->catalog->bankFor(PaymentMethod::Cheque));
        $this->assertNull($this->catalog->bankFor(PaymentMethod::PromissoryNote));
        $this->assertNull($this->catalog->bankFor(PaymentMethod::Offset));
    }

    public function test_with_payment_account_attaches_matching_bank_and_leaves_cheque_without_one(): void
    {
        $cash = $this->catalog->withPaymentAccount(['payment_method' => PaymentMethod::Cash->value]);
        $transfer = $this->catalog->withPaymentAccount(['payment_method' => PaymentMethod::BankTransfer->value]);
        $card = $this->catalog->withPaymentAccount(['payment_method' => PaymentMethod::CreditCard->value]);
        $cheque = $this->catalog->withPaymentAccount(['payment_method' => PaymentMethod::Cheque->value]);
        $kept = $this->catalog->withPaymentAccount([
            'payment_method' => PaymentMethod::Cash->value,
            'bank_id' => $this->catalog->banksOf(AccountType::Cash)->first()->id,
        ]);

        $this->assertSame(AccountType::Cash, Bank::query()->findOrFail($cash['bank_id'])->type);
        $this->assertSame(AccountType::Bank, Bank::query()->findOrFail($transfer['bank_id'])->type);
        $this->assertContains(
            Bank::query()->findOrFail($card['bank_id'])->type,
            [AccountType::Pos, AccountType::CreditCard],
        );
        $this->assertArrayNotHasKey('bank_id', $cheque);
        $this->assertSame($kept['bank_id'], $this->catalog->banksOf(AccountType::Cash)->first()->id);
    }

    public function test_seeded_payments_name_accounts_matching_their_payment_method(): void
    {
        $entries = app(CariEntryService::class);
        $supplier = Party::factory()->company()->withRole(PartyRole::Supplier)->create();
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner, '2026-01-01')->create(['plate' => '34 LT 38']);
        $dump = DumpSite::factory()->create();
        $category = $this->catalog->categoryFor(CashDirection::Expense)?->id;

        $samples = [
            ['kind' => 'supplier', 'direction' => 'debit', 'party_id' => $supplier->id, 'payment_method' => PaymentMethod::Cash->value, 'cash_category_id' => $category],
            ['kind' => 'supplier', 'direction' => 'debit', 'party_id' => $supplier->id, 'payment_method' => PaymentMethod::BankTransfer->value, 'cash_category_id' => $category],
            ['kind' => 'supplier', 'direction' => 'debit', 'party_id' => $supplier->id, 'payment_method' => PaymentMethod::CreditCard->value, 'cash_category_id' => $category],
            ['kind' => 'vehicle_payment', 'plate' => $vehicle->plate, 'payment_method' => PaymentMethod::Cash->value],
            ['kind' => 'dump_payment', 'dump_site_id' => $dump->id, 'payment_method' => PaymentMethod::BankTransfer->value],
            ['kind' => 'dump_payment', 'dump_site_id' => $dump->id, 'payment_method' => PaymentMethod::Cheque->value, 'cheque_no' => 'CK000001'],
        ];

        foreach ($samples as $sample) {
            $entries->create($this->catalog->withPaymentAccount([
                ...$sample,
                'amount' => '1000.00',
                'occurred_on' => '2026-02-15',
                'description' => '38b',
            ]), null);
        }

        foreach ([CompanyTransaction::class, VehiclePayment::class, DumpSitePayment::class] as $model) {
            $model::query()->get()->each(function ($row) {
                $method = $row->payment_method instanceof PaymentMethod
                    ? $row->payment_method
                    : PaymentMethod::from($row->payment_method);
                $types = $method->accountTypes();

                if ($types === []) {
                    $this->assertNull($row->bank_id, $method->label().' ödemesinde hesap olmamalı.');

                    return;
                }

                $this->assertNotNull($row->bank_id, $method->label().' ödemesinde hesap olmalı.');
                $account = Bank::query()->findOrFail($row->bank_id);
                $this->assertContains($account->type, $types, $method->label().' → '.$account->type->label());
            });
        }
    }
}

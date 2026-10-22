<?php

namespace Database\Seeders;

use App\Enums\EmployeeOccupation;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Enums\Shift;
use App\Models\Bank;
use App\Models\CashCategory;
use App\Models\Company;
use App\Models\DumpSite;
use App\Models\FuelStation;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CariEntryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills the tables the operation seeders leave empty: banks, groups, cari movements,
 * payroll, checks, cheques and notes. Sized for a usable demo, not a load test.
 */
class MediumDataSeeder extends Seeder
{
    private ?string $userId;

    public function run(CariEntryService $entries): void
    {
        $this->userId = User::query()->value('id');

        $sites = Site::query()->where('active', true)->get();
        $vehicles = Vehicle::query()->where('active', true)->get();
        $dumpSites = DumpSite::query()->where('active', true)->get();
        $categories = CashCategory::query()->where('active', true)->get();

        if ($sites->isEmpty() || $vehicles->isEmpty()) {
            return;
        }

        $banks = $this->banks();
        $this->companyGroups();
        $markets = $this->markets($sites);
        $staff = $this->staff();

        $this->cari($entries, $sites, $vehicles, $dumpSites, $categories, $markets);
        $this->payroll($staff);
        $this->operations($vehicles);
        $this->instruments($banks, $categories);
        $this->notes();
    }

    /**
     * @return Collection<int, Bank>
     */
    private function banks()
    {
        return collect([
            ['Ziraat Bankası', 'Ayşe Korkmaz', 'TR330006100519786457841326'],
            ['İş Bankası', 'Murat Aslan', 'TR120006400000112345678901'],
            ['Garanti BBVA', 'Selin Aksoy', 'TR640006200011100006672381'],
            ['VakıfBank', 'Kemal Uçar', 'TR980001500158007291234567'],
        ])->map(fn (array $bank) => Bank::query()->firstOrCreate(['name' => $bank[0]], [
            'representative' => $bank[1],
            'phone' => fake()->numerify('0212#######'),
            'account_no' => $bank[2],
        ]));
    }

    private function companyGroups(): void
    {
        $root = $this->group('Hafriyat Grubu');
        $groups = collect([
            $this->group('Nakliye', $root),
            $this->group('Döküm', $root),
            $this->group('Akaryakıt', $root),
        ]);

        Company::query()->whereNull('company_group_id')->each(function (Company $company, int $index) use ($groups): void {
            $company->update([
                'company_group_id' => $groups[$index % $groups->count()]->id,
                'tax_office' => 'İstanbul',
                'tax_number' => str_pad((string) (1000000000 + $index), 10, '0', STR_PAD_LEFT),
            ]);
        });
    }

    private function group(string $name, ?object $parent = null): object
    {
        return DB::table('company_groups')->where('name', $name)->first()
            ?? tap((object) ['id' => (string) Str::uuid7()], function (object $group) use ($name, $parent): void {
                DB::table('company_groups')->insert([
                    'id' => $group->id,
                    'parent_id' => $parent->id ?? null,
                    'name' => $name,
                    'sort_order' => 0,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return Collection<int, Party>
     */
    private function markets($sites)
    {
        return collect(['SAHA PİYASA TİC. LTD. ŞTİ.', 'KUZEY SPOT HAFRİYAT A.Ş.'])
            ->map(function (string $name) use ($sites) {
                $party = Party::query()->where('name', $name)->first()
                    ?? Party::factory()->company()->withRole(PartyRole::Market)->create(['name' => $name]);

                $sites->each(function (Site $site) use ($party): void {
                    $linked = $site->parties()
                        ->where('parties.id', $party->id)
                        ->wherePivot('role', PartyRole::Market->value)
                        ->exists();

                    if (! $linked) {
                        $site->parties()->attach($party->id, ['role' => PartyRole::Market->value]);
                    }
                });

                return $party;
            });
    }

    /**
     * @return Collection<int, Party>
     */
    private function staff()
    {
        $names = ['Murat Kaya', 'Zeynep Acar', 'Burak Şen', 'Elif Koç', 'Okan Yılmaz', 'Sevgi Aydın'];

        return collect($names)->map(function (string $name) {
            return Party::query()->where('name', $name)->first()
                ?? Party::factory()->person()->withRole(PartyRole::Employee)->create(['name' => $name]);
        });
    }

    private function cari(CariEntryService $entries, $sites, $vehicles, $dumpSites, $categories, $markets): void
    {
        $day = fn () => today()->subDays(fake()->numberBetween(0, 20))->toDateString();
        $method = fn () => fake()->randomElement(PaymentMethod::values());
        $category = fn () => $categories->random()->id;
        $parties = fn (PartyRole $role) => Party::query()->whereHas('roleAssignments', fn ($query) => $query->where('role', $role))->get();

        foreach ($parties(PartyRole::Supplier)->take(4) as $party) {
            foreach (range(1, 4) as $n) {
                $entries->create([
                    'kind' => 'supplier',
                    'direction' => $n % 2 === 0 ? 'credit' : 'debit',
                    'party_id' => $party->id,
                    'site_id' => $sites->random()->id,
                    'amount' => fake()->numberBetween(2500, 18000),
                    'occurred_on' => $day(),
                    'payment_method' => $method(),
                    'cash_category_id' => $category(),
                    'document_no' => 'ST-'.fake()->unique()->numerify('####'),
                    'description' => fake()->randomElement(['Kum', 'Çakıl', 'Demir', 'Beton katkısı']),
                ], $this->userId);
            }
        }

        foreach ($parties(PartyRole::Customer)->take(3) as $party) {
            foreach (range(1, 3) as $ignored) {
                $entries->create([
                    'kind' => 'customer',
                    'direction' => 'credit',
                    'party_id' => $party->id,
                    'site_id' => $sites->random()->id,
                    'amount' => fake()->numberBetween(8000, 40000),
                    'occurred_on' => $day(),
                    'payment_method' => $method(),
                    'description' => 'Hakediş',
                ], $this->userId);
            }
        }

        $fuel = $parties(PartyRole::Fuel)->first();
        $station = FuelStation::query()->where('active', true)->inRandomOrder()->first();

        if ($fuel && $station) {
            foreach (range(1, 6) as $ignored) {
                $liters = fake()->randomElement([500, 1000, 1500, 2000]);
                $entries->create([
                    'kind' => 'fuel',
                    'direction' => 'credit',
                    'party_id' => $fuel->id,
                    'site_id' => $sites->random()->id,
                    'fuel_station_id' => $station->id,
                    'amount' => round($liters * (float) $station->unit_price, 2),
                    'liters' => $liters,
                    'unit_price' => $station->unit_price,
                    'occurred_on' => $day(),
                    'payment_method' => PaymentMethod::BankTransfer->value,
                    'document_no' => 'AK-'.fake()->unique()->numerify('####'),
                    'description' => 'Akaryakıt faturası',
                ], $this->userId);
            }
        }

        foreach ($markets as $market) {
            foreach ($vehicles->random(3) as $vehicle) {
                $entries->create([
                    'kind' => 'market',
                    'direction' => 'debit',
                    'party_id' => $market->id,
                    'plate' => $vehicle->plate,
                    'site_id' => $sites->random()->id,
                    'amount' => fake()->numberBetween(1500, 9000),
                    'occurred_on' => $day(),
                    'payment_method' => PaymentMethod::Cash->value,
                    'description' => 'Piyasa seferi',
                ], $this->userId);
            }
        }

        foreach ($vehicles->random(min(10, $vehicles->count())) as $vehicle) {
            $entries->create([
                'kind' => 'vehicle_payment',
                'plate' => $vehicle->plate,
                'amount' => fake()->numberBetween(1000, 12000),
                'occurred_on' => $day(),
                'payment_method' => $method(),
                'description' => 'Plaka ödemesi',
            ], $this->userId);

            $entries->create([
                'kind' => 'vehicle_receivable',
                'plate' => $vehicle->plate,
                'amount' => fake()->numberBetween(500, 6000),
                'occurred_on' => $day(),
                'payment_method' => PaymentMethod::Offset->value,
                'description' => 'Plaka alacağı',
            ], $this->userId);
        }

        foreach ($parties(PartyRole::VehicleOwner)->take(6) as $owner) {
            $entries->create([
                'kind' => 'owner_payment',
                'party_id' => $owner->id,
                'amount' => fake()->numberBetween(2000, 15000),
                'occurred_on' => $day(),
                'payment_method' => $method(),
                'cash_category_id' => $category(),
                'description' => 'Araç sahibi ödemesi',
            ], $this->userId);
        }

        foreach ($dumpSites as $dumpSite) {
            $entries->create([
                'kind' => 'dump_payment',
                'dump_site_id' => $dumpSite->id,
                'amount' => fake()->numberBetween(4000, 20000),
                'occurred_on' => $day(),
                'payment_method' => PaymentMethod::Cheque->value,
                'cheque_no' => fake()->unique()->numerify('CK####'),
                'description' => 'Döküm bedeli',
            ], $this->userId);
        }

        foreach ($sites as $site) {
            foreach (['debit', 'credit'] as $direction) {
                $entries->create([
                    'kind' => 'site',
                    'direction' => $direction,
                    'site_id' => $site->id,
                    'amount' => fake()->numberBetween(1500, 12000),
                    'occurred_on' => $day(),
                    'payment_method' => $method(),
                    'description' => $direction === 'debit' ? 'Şantiye geliri' : 'Şantiye gideri',
                ], $this->userId);
            }
        }

        $bankId = Bank::query()->value('id');

        foreach (range(1, 12) as $n) {
            $entries->create([
                'kind' => 'cash',
                'direction' => $n % 3 === 0 ? 'credit' : 'debit',
                'amount' => fake()->numberBetween(500, 25000),
                'occurred_on' => $day(),
                'cash_category_id' => $category(),
                'bank_id' => $n % 2 === 0 ? $bankId : null,
                'description' => fake()->randomElement(['Bankadan çekilen', 'Kasaya yatan', 'Ofis gideri', 'Nakit ihtiyaç']),
            ], $this->userId);
        }

        $this->rows('other_payments', collect(range(1, 8))->map(fn () => [
            'payment_method' => $method(),
            'cash_category_id' => $category(),
            'amount' => fake()->numberBetween(200, 4000),
            'occurred_on' => $day(),
            'description' => fake()->randomElement(['Kırtasiye', 'Yemek', 'Kargo', 'Ulaşım']),
            'created_by' => $this->userId,
        ])->all());
    }

    /**
     * @param  Collection<int, Party>  $staff
     */
    private function payroll($staff): void
    {
        $occupations = EmployeeOccupation::cases();
        $from = now()->subMonths(11)->startOfMonth();
        $last = now()->startOfMonth()->subMonth();
        $periods = [];
        $cursor = $from->copy();

        while ($cursor->lte($last)) {
            $periods[] = $cursor->toDateString();
            $cursor->addMonth();
        }

        foreach ($staff->values() as $index => $person) {
            if (DB::table('employees')->where('party_id', $person->id)->whereNull('deleted_at')->exists()) {
                continue;
            }

            $employeeId = (string) Str::uuid7();
            $baseSalary = fake()->randomElement([28000, 35000, 42000, 55000]);
            $startedOn = $from->copy()->addDays(5)->toDateString();
            $currentBase = $baseSalary;

            DB::table('employees')->insert([
                'id' => $employeeId,
                'party_id' => $person->id,
                'started_on' => $startedOn,
                'occupation' => $occupations[$index % count($occupations)]->value,
                'salary' => $baseSalary,
                'bank_account_no' => fake()->numerify('TR########################'),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employee_periods')->insert([
                'id' => (string) Str::uuid7(),
                'employee_id' => $employeeId,
                'started_on' => $startedOn,
                'ended_on' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($periods as $monthIndex => $period) {
                if ($monthIndex === 6) {
                    $currentBase = round($currentBase * 1.08, 2);
                }

                $days = fake()->numberBetween(20, 26);
                $earned = round($currentBase / 30 * $days, 2);

                DB::table('employee_salaries')->insert([
                    'id' => (string) Str::uuid7(),
                    'employee_id' => $employeeId,
                    'period' => $period,
                    'base_salary' => $currentBase,
                    'worked_days' => $days,
                    'earned_salary' => $earned,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('employee_payments')->insert([
                    'id' => (string) Str::uuid7(),
                    'employee_id' => $employeeId,
                    'payment_method' => PaymentMethod::BankTransfer->value,
                    'amount' => $earned,
                    'period' => $period,
                    'occurred_on' => Carbon::parse($period)->day(5)->toDateString(),
                    'description' => Carbon::parse($period)->translatedFormat('F Y').' maaşı',
                    'created_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($index % 2 === 0 && $monthIndex % 2 === 1) {
                    DB::table('employee_bonuses')->insert([
                        'id' => (string) Str::uuid7(),
                        'employee_id' => $employeeId,
                        'amount' => fake()->randomElement([2500, 4000, 7500]),
                        'occurred_on' => Carbon::parse($period)->day(15)->toDateString(),
                        'description' => 'Saha pirimi',
                        'created_by' => $this->userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('employees')->where('id', $employeeId)->update(['salary' => $currentBase]);
        }
    }

    private function operations($vehicles): void
    {
        $routes = HaulRoute::query()->get();
        $stations = FuelStation::query()->where('active', true)->get();
        $hauls = Haul::query()->latest('hauled_at')->limit(24)->get();

        $this->rows('haul_dumps', $hauls->map(fn (Haul $haul) => [
            'haul_id' => $haul->id,
            'vehicle_id' => $haul->vehicle_id,
            'dump_site_id' => $haul->dump_site_id,
            'price' => $haul->dump_price,
            'tonnage' => $haul->tonnage,
            'dumped_on' => $haul->hauled_at->toDateString(),
        ])->all());

        $this->rows('ticket_handovers', $vehicles->random(min(12, $vehicles->count()))->map(fn (Vehicle $vehicle) => [
            'vehicle_id' => $vehicle->id,
            'route_id' => $routes->random()->id,
            'haul_count' => fake()->numberBetween(1, 8),
            'fuel_count' => fake()->numberBetween(0, 3),
            'description' => 'Gün sonu fiş teslimi',
            'handed_on' => today()->subDays(fake()->numberBetween(0, 14))->toDateString(),
            'shift' => fake()->randomElement([Shift::Day->value, Shift::Night->value]),
            'created_by' => $this->userId,
        ])->all());

        $this->rows('dump_checks', $routes->random(min(8, $routes->count()))->map(fn (HaulRoute $route) => [
            'route_id' => $route->id,
            'haul_count' => fake()->numberBetween(4, 20),
            'description' => 'Döküm sayım kontrolü',
            'checked_on' => today()->subDays(fake()->numberBetween(0, 14))->toDateString(),
            'shift' => Shift::Day->value,
            'user_id' => $this->userId,
        ])->all());

        $this->rows('fuel_checks', $stations->map(fn (FuelStation $station) => [
            'fuel_station_id' => $station->id,
            'haul_count' => fake()->numberBetween(2, 12),
            'fuel_count' => fake()->numberBetween(2, 10),
            'description' => 'İstasyon fiş kontrolü',
            'checked_on' => today()->subDays(fake()->numberBetween(0, 10))->toDateString(),
            'shift' => Shift::Day->value,
            'user_id' => $this->userId,
        ])->all());

        $this->rows('vehicle_fines', $vehicles->random(min(8, $vehicles->count()))->map(function (Vehicle $vehicle) use ($routes) {
            $paid = fake()->boolean();

            return [
                'vehicle_id' => $vehicle->id,
                'party_id' => $vehicle->currentOwnership?->party_id,
                'route_id' => $routes->random()->id,
                'amount' => fake()->randomElement([690, 1506, 3140]),
                'occurred_on' => today()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                'document_no' => fake()->unique()->numerify('CZ######'),
                'driver_name' => $vehicle->driver_name,
                'status' => $paid ? 'paid' : 'pending',
                'paid_on' => $paid ? today()->toDateString() : null,
                'payment_method' => $paid ? PaymentMethod::Cash->value : null,
                'description' => 'Hız cezası',
                'created_by' => $this->userId,
            ];
        })->filter(fn (array $row) => $row['party_id'] !== null)->all());
    }

    private function instruments($banks, $categories): void
    {
        $parties = Party::query()->where('type', 'company')->limit(6)->get();
        $cheque = null;

        foreach (range(1, 6) as $n) {
            $id = (string) Str::uuid7();
            $amount = fake()->numberBetween(5000, 40000);
            $received = $n % 2 === 1;

            DB::table('financial_instruments')->insert([
                'id' => $id,
                'kind' => 'cheque',
                'status' => $n === 6 ? 'closed' : 'active',
                'debit' => $received ? $amount : 0,
                'credit' => $received ? 0 : $amount,
                'issued_on' => today()->subDays(20)->toDateString(),
                'due_on' => today()->addDays(15 * $n)->toDateString(),
                'party_id' => $parties->random()->id,
                'description' => $received ? 'Alınan çek' : 'Verilen çek',
                'bank_id' => $banks->random()->id,
                'document_no' => 'CEK-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'received_no' => $received ? 'AL-'.$n : null,
                'given_no' => $received ? null : 'VR-'.$n,
                'cash_category_id' => $categories->random()->id,
                'endorsed_from_id' => $n === 5 ? $cheque : null,
                'created_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cheque ??= $id;

            if ($n === 1) {
                DB::table('financial_instrument_attachments')->insert([
                    'id' => (string) Str::uuid7(),
                    'financial_instrument_id' => $id,
                    'path' => 'instruments/cek-1.pdf',
                    'secondary_path' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (range(1, 4) as $n) {
            DB::table('financial_instruments')->insert([
                'id' => (string) Str::uuid7(),
                'kind' => 'promissory_note',
                'status' => 'active',
                'debit' => $n % 2 === 0 ? 0 : fake()->numberBetween(3000, 15000),
                'credit' => $n % 2 === 0 ? fake()->numberBetween(3000, 15000) : 0,
                'issued_on' => today()->subDays(10)->toDateString(),
                'due_on' => today()->addDays(30 * $n)->toDateString(),
                'party_id' => $parties->random()->id,
                'description' => 'Senet',
                'document_no' => 'SNT-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'created_by' => $this->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $loanId = (string) Str::uuid7();

        DB::table('financial_instruments')->insert([
            'id' => $loanId,
            'kind' => 'loan',
            'status' => 'active',
            'debit' => 0,
            'credit' => 120000,
            'issued_on' => '2026-06-01',
            'due_on' => '2026-11-01',
            'party_id' => null,
            'related_party' => 'Ziraat Bankası',
            'description' => 'İşletme kredisi',
            'installment_count' => 3,
            'bank_id' => $banks->first()->id,
            'account_no' => $banks->first()->account_no,
            'document_no' => 'KRD-0001',
            'cash_category_id' => $categories->random()->id,
            'created_by' => $this->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1, 2, 3] as $installment) {
            $paid = $installment === 1;

            DB::table('financial_instrument_installments')->insert([
                'id' => (string) Str::uuid7(),
                'financial_instrument_id' => $loanId,
                'amount' => 40000,
                'due_on' => today()->addMonths($installment - 1)->startOfMonth()->toDateString(),
                'paid_on' => $paid ? today()->subMonth()->toDateString() : null,
                'status' => $paid ? 'paid' : 'active',
                'receipt_no' => $paid ? 'DK-1001' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function notes(): void
    {
        $this->rows('notes', collect(range(1, 10))->map(fn () => [
            'user_id' => $this->userId,
            'body' => fake()->randomElement([
                'Yarın Bolluca döküm sahası kapalı, seferleri Ağaçlı’ya çevir.',
                '34 AL 4927 lastik değişimi bekliyor.',
                'Akaryakıt faturası cuma günü kesilecek.',
                'Şantiye hakedişi onay bekliyor.',
            ]),
            'noted_on' => today()->subDays(fake()->numberBetween(0, 20))->toDateString(),
        ])->all());

        $party = Party::query()->first();

        $this->rows('audit_logs', collect(range(1, 12))->map(fn (int $n) => [
            'user_id' => $this->userId,
            'channel' => $n % 3 === 0 ? 'api' : 'web',
            'action' => $n % 5 === 0 ? 'updated' : ($n % 4 === 0 ? 'viewed' : 'created'),
            'auditable_type' => 'party',
            'auditable_id' => $party?->id,
            'ip_address' => '127.0.0.1',
            'method' => $n % 4 === 0 ? 'GET' : 'POST',
            'route' => $n % 3 === 0 ? 'api.hauls.store' : 'admin.parties.store',
            'url' => $n % 3 === 0 ? 'http://localhost/api/v1/hauls' : 'http://localhost/admin/parties',
            'status' => 200,
            'old_values' => $n % 5 === 0 ? json_encode(['phone' => '05000000000']) : null,
            'new_values' => json_encode(['name' => $party?->name]),
            'created_at' => now()->subDays($n),
        ])->all(), timestamps: false);

        Party::query()->limit(8)->get()->each(function (Party $party, int $index): void {
            DB::table('legacy_id_maps')->insert([
                'id' => (string) Str::uuid7(),
                'legacy_table' => 'cari',
                'legacy_id' => (string) (1000 + $index),
                'new_table' => 'parties',
                'new_id' => $party->id,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function rows(string $table, array $rows, bool $timestamps = true): void
    {
        if ($rows === []) {
            return;
        }

        DB::table($table)->insert(array_map(function (array $row) use ($timestamps) {
            $row['id'] ??= (string) Str::uuid7();

            if ($timestamps) {
                $row['created_at'] ??= now();
                $row['updated_at'] ??= now();
            }

            return $row;
        }, $rows));
    }
}

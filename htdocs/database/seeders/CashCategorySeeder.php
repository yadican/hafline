<?php

namespace Database\Seeders;

use App\Enums\CashDirection;
use App\Models\CashCategory;
use Illuminate\Database\Seeder;

/**
 * Starting expense/income items, cleaned up from the legacy "cari_kategori" list. Items that have
 * their own screen (fuel, wages, cheques, loans, hauling) are left out on purpose.
 */
class CashCategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Yemek' => CashDirection::Expense,
        'Lastik' => CashDirection::Expense,
        'Tamir ve Bakım' => CashDirection::Expense,
        'Yedek Parça' => CashDirection::Expense,
        'Hırdavat' => CashDirection::Expense,
        'Sigorta' => CashDirection::Expense,
        'Ofis Giderleri' => CashDirection::Expense,
        'Banka Kesintileri' => CashDirection::Expense,
        'Demirbaş Alımı' => CashDirection::Expense,
        'Hizmet Alımı' => CashDirection::Expense,
        'Makine Kirası' => CashDirection::Expense,
        'Nakit ihtiyaç' => CashDirection::Expense,
        'Bankadan Çekilen' => CashDirection::Income,
        'Diğer Gelir' => CashDirection::Income,
        'Teminat' => CashDirection::Both,
        'İş Avansı' => CashDirection::Both,
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $direction) {
            CashCategory::query()->firstOrCreate(['name' => $name], ['direction' => $direction]);
        }
    }
}

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
        'Tamir ve bakım' => CashDirection::Expense,
        'Yedek parça' => CashDirection::Expense,
        'Hırdavat' => CashDirection::Expense,
        'Sigorta' => CashDirection::Expense,
        'Ofis giderleri' => CashDirection::Expense,
        'Banka kesintileri' => CashDirection::Expense,
        'Demirbaş alımı' => CashDirection::Expense,
        'Hizmet alımı' => CashDirection::Expense,
        'Makine kirası' => CashDirection::Expense,
        'Nakit ihtiyaç' => CashDirection::Expense,
        'Bankadan çekilen' => CashDirection::Income,
        'Diğer gelir' => CashDirection::Income,
        'Teminat' => CashDirection::Both,
        'İş avansı' => CashDirection::Both,
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $direction) {
            CashCategory::query()->firstOrCreate(['name' => $name], ['direction' => $direction]);
        }
    }
}

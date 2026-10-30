<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\StockItem;
use App\Models\StockUsage;
use App\Repositories\StockUsageRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Own-use stock exits: freeze that day's average cost, drop book stock, record the cost as an
 * expense (site expense report when a site is set). No cari party.
 */
class StockUsageService extends Service
{
    public function __construct(
        private StockUsageRepository $usages,
        private StockService $stock,
    ) {}

    /**
     * @param  array{q?: string, stock_item_id?: string, site_id?: string, vehicle_id?: string, from?: string, to?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->usages->search($filters, $sort, $perPage);
    }

    /**
     * @return array{stock_items: array<string, string>, sites: array<string, string>, vehicles: array<string, string>, categories: array<string, string>}
     */
    public function formOptions(?StockUsage $usage = null): array
    {
        return [
            'stock_items' => $this->usages->stockItemOptions($usage?->stock_item_id),
            'sites' => $this->usages->siteOptions($usage?->site_id),
            'vehicles' => $this->usages->vehicleOptions($usage?->vehicle_id),
            'categories' => $this->usages->categoryOptions($usage?->cash_category_id),
        ];
    }

    /**
     * @param  array{stock_item_id: string, quantity: numeric-string|float, used_on: string, site_id?: ?string, vehicle_id?: ?string, cash_category_id: string, description?: ?string}  $data
     */
    public function create(array $data): StockUsage
    {
        return $this->transaction(function () use ($data): StockUsage {
            $item = $this->trackedItem($data['stock_item_id']);
            $quantity = round((float) $data['quantity'], 3);
            $usedOn = Carbon::parse($data['used_on']);

            $this->stock->assertUsageKeepsStockNonNegative($item, $usedOn, $quantity);

            $unitCost = $this->stock->stock($item, $usedOn)['average_cost'];
            $amount = round($quantity * $unitCost, 2);

            /** @var StockUsage $usage */
            $usage = $this->usages->create([
                ...$this->attributes($data, $quantity, $unitCost, $amount),
                'created_by' => Auth::id(),
            ]);

            return $usage;
        });
    }

    /**
     * @param  array{stock_item_id: string, quantity: numeric-string|float, used_on: string, site_id?: ?string, vehicle_id?: ?string, cash_category_id: string, description?: ?string}  $data
     */
    public function update(StockUsage $usage, array $data): StockUsage
    {
        return $this->transaction(function () use ($usage, $data): StockUsage {
            $item = $this->trackedItem($data['stock_item_id']);
            $quantity = round((float) $data['quantity'], 3);
            $usedOn = Carbon::parse($data['used_on']);

            $this->stock->assertUsageKeepsStockNonNegative($item, $usedOn, $quantity, $usage->id);

            $unitCost = $this->stock->stock($item, $usedOn, exceptUsageId: $usage->id)['average_cost'];
            $amount = round($quantity * $unitCost, 2);

            /** @var StockUsage $updated */
            $updated = $this->usages->update($usage, $this->attributes($data, $quantity, $unitCost, $amount));

            return $updated;
        });
    }

    public function delete(StockUsage $usage): void
    {
        $this->transaction(function () use ($usage): void {
            $this->usages->delete($usage);
        });
    }

    /**
     * @param  array{stock_item_id: string, used_on: string, site_id?: ?string, vehicle_id?: ?string, cash_category_id: string, description?: ?string}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, float $quantity, float $unitCost, float $amount): array
    {
        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            $item = StockItem::query()->find($data['stock_item_id']);
            $description = sprintf(
                'Stok kullanımı · %s · %s',
                $item?->name ?? 'kalem',
                rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ','),
            );
        }

        return [
            'stock_item_id' => $data['stock_item_id'],
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'amount' => $amount,
            'used_on' => $data['used_on'],
            'site_id' => $data['site_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'cash_category_id' => $data['cash_category_id'],
            'description' => $description,
        ];
    }

    private function trackedItem(string $id): StockItem
    {
        $item = StockItem::query()->find($id)
            ?? throw new OperationRejectedException('Stok kalemi bulunamadı.');

        if (! $item->tracks_stock) {
            throw new OperationRejectedException(sprintf('%s stok takibi yapılmıyor; kullanım kaydı açılamaz.', $item->name));
        }

        return $item;
    }
}

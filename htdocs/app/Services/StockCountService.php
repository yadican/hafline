<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Repositories\StockCountRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Physical stock counts: difference (counted − book that day) adjusts stock. No cari posting.
 * Upward difference enters at the running average (0 when empty); downward exits like usage.
 */
class StockCountService extends Service
{
    public function __construct(
        private StockCountRepository $counts,
        private StockService $stock,
    ) {}

    /**
     * @param  array{q?: string, stock_item_id?: string, from?: string, to?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->counts->search($filters, $sort, $perPage);
    }

    /**
     * @return array{stock_items: array<string, string>}
     */
    public function formOptions(?StockCount $count = null): array
    {
        return [
            'stock_items' => $this->counts->stockItemOptions($count?->stock_item_id),
        ];
    }

    /**
     * @param  array{stock_item_id: string, quantity: numeric-string|float, counted_on: string, description?: ?string}  $data
     */
    public function create(array $data): StockCount
    {
        return $this->transaction(function () use ($data): StockCount {
            $item = $this->trackedItem($data['stock_item_id']);
            $quantity = round((float) $data['quantity'], 3);
            $countedOn = Carbon::parse($data['counted_on']);
            $book = $this->stock->stock($item, $countedOn)['quantity'];
            $difference = round($quantity - $book, 3);

            $this->stock->assertCountKeepsStockNonNegative($item, $countedOn, $difference);

            /** @var StockCount $count */
            $count = $this->counts->create([
                ...$this->attributes($data, $quantity, $difference),
                'created_by' => Auth::id(),
            ]);

            return $count;
        });
    }

    /**
     * @param  array{stock_item_id: string, quantity: numeric-string|float, counted_on: string, description?: ?string}  $data
     */
    public function update(StockCount $count, array $data): StockCount
    {
        return $this->transaction(function () use ($count, $data): StockCount {
            $item = $this->trackedItem($data['stock_item_id']);
            $quantity = round((float) $data['quantity'], 3);
            $countedOn = Carbon::parse($data['counted_on']);
            $book = $this->stock->stock($item, $countedOn, exceptCountId: $count->id)['quantity'];
            $difference = round($quantity - $book, 3);

            $this->stock->assertCountKeepsStockNonNegative($item, $countedOn, $difference, $count->id);

            /** @var StockCount $updated */
            $updated = $this->counts->update($count, $this->attributes($data, $quantity, $difference));

            return $updated;
        });
    }

    public function delete(StockCount $count): void
    {
        $this->transaction(function () use ($count): void {
            $this->counts->delete($count);
        });
    }

    /**
     * @param  array{stock_item_id: string, counted_on: string, description?: ?string}  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, float $quantity, float $difference): array
    {
        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            $item = StockItem::query()->find($data['stock_item_id']);
            $sign = $difference > 0 ? '+' : '';
            $description = sprintf(
                'Sayım farkı · %s · %s%s',
                $item?->name ?? 'kalem',
                $sign,
                rtrim(rtrim(number_format($difference, 3, ',', '.'), '0'), ','),
            );
        }

        return [
            'stock_item_id' => $data['stock_item_id'],
            'counted_on' => $data['counted_on'],
            'quantity' => $quantity,
            'difference' => $difference,
            'description' => $description,
        ];
    }

    private function trackedItem(string $id): StockItem
    {
        $item = StockItem::query()->find($id)
            ?? throw new OperationRejectedException('Stok kalemi bulunamadı.');

        if (! $item->tracks_stock) {
            throw new OperationRejectedException(sprintf('%s stok takibi yapılmıyor; sayım kaydı açılamaz.', $item->name));
        }

        return $item;
    }
}

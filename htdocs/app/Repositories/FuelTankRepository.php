<?php

namespace App\Repositories;

use App\Models\FuelReceipt;
use App\Models\FuelStation;
use App\Models\FuelTankCount;
use App\Models\FuelTankFill;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Stock movements of one of our own tanks: fills in, receipts out, count corrections.
 */
class FuelTankRepository
{
    /**
     * Movements up to the date (all when null) in day order; on the same day fills come first so
     * fuel bought that morning is in stock for that day's receipts.
     *
     * @return Collection<int, array{date: string, kind: 'fill'|'receipt'|'count', quantity: float, amount: float}>
     */
    public function movements(FuelStation $tank, ?CarbonInterface $until = null): Collection
    {
        $day = $until?->toDateString();

        $fills = FuelTankFill::query()
            ->where('fuel_station_id', $tank->id)
            ->when($day, fn ($query) => $query->whereDate('filled_on', '<=', $day))
            ->get(['filled_on', 'quantity', 'amount'])
            ->map(fn (FuelTankFill $fill): array => ['date' => $fill->filled_on->toDateString(), 'kind' => 'fill', 'quantity' => (float) $fill->quantity, 'amount' => (float) $fill->amount]);

        $receipts = FuelReceipt::query()
            ->where('fuel_station_id', $tank->id)
            ->when($day, fn ($query) => $query->whereDate('issued_on', '<=', $day))
            ->selectRaw('issued_on, sum(quantity) as quantity')
            ->groupBy('issued_on')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['date' => substr((string) $row->issued_on, 0, 10), 'kind' => 'receipt', 'quantity' => -(float) $row->quantity, 'amount' => 0.0]);

        $counts = FuelTankCount::query()
            ->where('fuel_station_id', $tank->id)
            ->when($day, fn ($query) => $query->whereDate('counted_on', '<=', $day))
            ->get(['counted_on', 'difference'])
            ->map(fn (FuelTankCount $count): array => ['date' => $count->counted_on->toDateString(), 'kind' => 'count', 'quantity' => (float) $count->difference, 'amount' => 0.0]);

        $order = ['fill' => 0, 'receipt' => 1, 'count' => 2];

        return $fills->concat($receipts)->concat($counts)
            ->sortBy([fn (array $a, array $b): int => [$a['date'], $order[$a['kind']]] <=> [$b['date'], $order[$b['kind']]]])
            ->values();
    }

    /**
     * @return Collection<int, FuelTankFill>
     */
    public function fills(FuelStation $tank): Collection
    {
        return FuelTankFill::query()->with('party')->where('fuel_station_id', $tank->id)->latest('filled_on')->latest()->get();
    }

    /**
     * @return Collection<int, FuelTankCount>
     */
    public function counts(FuelStation $tank): Collection
    {
        return FuelTankCount::query()->where('fuel_station_id', $tank->id)->latest('counted_on')->latest()->get();
    }
}

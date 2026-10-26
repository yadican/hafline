<?php

namespace App\Services\Api;

use App\Models\FuelReceipt;
use App\Models\Haul;
use App\Models\MachineWork;
use App\Models\User;
use App\Services\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Mobile dashboard: only the signed-in user's own tickets, no cash data.
 */
class FieldDashboardService extends Service
{
    private const RECENT_LIMIT = 10;

    public function __construct(private FieldHaulService $fieldHauls) {}

    /**
     * @return array{
     *     field_session: ?array<string, mixed>,
     *     counts: array{
     *         hauls: array{today: int, yesterday: int, month: int},
     *         machine_works: array{today: int, yesterday: int, month: int},
     *         fuel_receipts: array{today: int, yesterday: int, month: int}
     *     },
     *     recent: list<array{type: string, id: string, time: string, plate: ?string, summary: string}>
     * }
     */
    public function summary(User $user, ?Carbon $today = null): array
    {
        $today ??= today();

        return [
            'field_session' => $this->fieldHauls->currentSession($user),
            'counts' => [
                'hauls' => $this->countsFor(Haul::class, 'hauled_at', $user, $today),
                'machine_works' => $this->countsFor(MachineWork::class, 'worked_on', $user, $today),
                'fuel_receipts' => $this->countsFor(FuelReceipt::class, 'issued_on', $user, $today),
            ],
            'recent' => $this->recent($user, $today),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @return array{today: int, yesterday: int, month: int}
     */
    private function countsFor(string $model, string $column, User $user, Carbon $today): array
    {
        $yesterday = $today->copy()->subDay();

        return [
            'today' => $this->countBetween($model, $column, $user, $today->copy()->startOfDay(), $today->copy()->addDay()->startOfDay()),
            'yesterday' => $this->countBetween($model, $column, $user, $yesterday->copy()->startOfDay(), $today->copy()->startOfDay()),
            'month' => $this->countBetween($model, $column, $user, $today->copy()->startOfMonth(), $today->copy()->addDay()->startOfDay()),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function countBetween(string $model, string $column, User $user, Carbon $from, Carbon $until): int
    {
        return $model::query()
            ->where('created_by', $user->id)
            ->where($column, '>=', $from)
            ->where($column, '<', $until)
            ->count();
    }

    /**
     * Tickets the user entered today across all three types, newest first.
     *
     * @return list<array{type: string, id: string, time: string, plate: ?string, summary: string}>
     */
    private function recent(User $user, Carbon $today): array
    {
        $from = $today->copy()->startOfDay();
        $until = $today->copy()->addDay()->startOfDay();

        $hauls = Haul::query()
            ->with(['vehicle:id,plate', 'site:id,name', 'dumpSite:id,name'])
            ->where('created_by', $user->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Haul $haul): array => $this->recentItem(
                type: 'haul',
                model: $haul,
                plate: $haul->vehicle?->plate,
                summary: ($haul->site?->name ?? '—').' → '.($haul->dumpSite?->name ?? '—'),
            ));

        $works = MachineWork::query()
            ->with(['vehicle:id,plate', 'site:id,name'])
            ->where('created_by', $user->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (MachineWork $work): array => $this->recentItem(
                type: 'machine_work',
                model: $work,
                plate: $work->vehicle?->plate,
                summary: ($work->site?->name ?? '—').' · '.MachineWork::formatHours($work->hours).' saat',
            ));

        $receipts = FuelReceipt::query()
            ->with(['vehicle:id,plate', 'fuelStation:id,name'])
            ->where('created_by', $user->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->latest()
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (FuelReceipt $receipt): array => $this->recentItem(
                type: 'fuel_receipt',
                model: $receipt,
                plate: $receipt->vehicle?->plate,
                summary: ($receipt->fuelStation?->name ?? '—').' · '.FuelReceipt::formatQuantity($receipt->quantity).' L',
            ));

        return $hauls
            ->toBase()
            ->merge($works)
            ->merge($receipts)
            ->sortByDesc('created_at')
            ->take(self::RECENT_LIMIT)
            ->map(fn (array $item): array => [
                'type' => $item['type'],
                'id' => $item['id'],
                'time' => $item['time'],
                'plate' => $item['plate'],
                'summary' => $item['summary'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{type: string, id: string, time: string, plate: ?string, summary: string, created_at: Carbon}
     */
    private function recentItem(string $type, Haul|MachineWork|FuelReceipt $model, ?string $plate, string $summary): array
    {
        return [
            'type' => $type,
            'id' => $model->id,
            'time' => $model->created_at->timezone(config('app.timezone'))->format('H:i'),
            'plate' => $plate,
            'summary' => $summary,
            'created_at' => $model->created_at,
        ];
    }
}

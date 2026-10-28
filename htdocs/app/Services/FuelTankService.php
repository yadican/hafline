<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\FuelStation;
use App\Models\FuelTankCount;
use App\Models\FuelTankFill;
use App\Models\Party;
use App\Repositories\FuelTankRepository;
use App\Repositories\PartyRepository;
use App\Services\Ledger\Ledger;
use App\Services\Ledger\LedgerLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Our own tank: a fill credits its supplier and adds stock at its price; receipts take litres out
 * at the weighted average cost; a count corrects the stock to what was measured.
 */
class FuelTankService extends Service
{
    public function __construct(
        private FuelTankRepository $tanks,
        private PartyRepository $parties,
        private Ledger $ledger,
    ) {}

    /**
     * Book stock and its weighted average cost on the date (today when null).
     *
     * @return array{quantity: float, average_cost: float, value: float}
     */
    public function stock(FuelStation $tank, ?CarbonInterface $on = null): array
    {
        $quantity = 0.0;
        $value = 0.0;
        $average = 0.0;

        foreach ($this->tanks->movements($tank, $on) as $movement) {
            if ($movement['kind'] === 'fill') {
                $price = $movement['amount'] / $movement['quantity'];
                $wasEmpty = $quantity <= 0;
                $quantity += $movement['quantity'];
                $value = $wasEmpty ? $quantity * $price : $value + $movement['amount'];
                $average = $quantity > 0 ? $value / $quantity : $price;

                continue;
            }

            $quantity += $movement['quantity'];
            $value = $quantity * $average;
        }

        return [
            'quantity' => round($quantity, 2),
            'average_cost' => round($average, 4),
            'value' => round($value, 2),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function supplierOptions(): array
    {
        return $this->parties->options(PartyRole::Fuel);
    }

    /**
     * @param  array{party_id: string, filled_on: string, quantity: string, unit_price: string, document_no?: ?string, description?: ?string}  $data
     */
    public function addFill(FuelStation $tank, array $data, ?string $userId): FuelTankFill
    {
        $this->assertTank($tank);

        return $this->transaction(function () use ($tank, $data, $userId): FuelTankFill {
            $party = Party::query()->findOrFail($data['party_id']);
            $party->assignRole(PartyRole::Fuel);
            FormOptionsCache::flush(FormOptionsCache::PARTIES);

            /** @var FuelTankFill $fill */
            $fill = $tank->fills()->create([...$data, 'created_by' => $userId]);
            $fill->refresh();

            $this->ledger->sync($fill, [
                LedgerLine::credit(
                    $fill->party_id,
                    $fill->filled_on,
                    $fill->total(),
                    sprintf('Depo dolumu%s · %s · %s lt', $fill->document_no ? ' '.$fill->document_no : '', $tank->name, rtrim(rtrim(number_format((float) $fill->quantity, 2, ',', '.'), '0'), ',')),
                ),
            ]);

            return $fill;
        });
    }

    public function deleteFill(FuelTankFill $fill): void
    {
        $this->transaction(function () use ($fill): void {
            $this->ledger->void($fill);
            $fill->delete();
        });
    }

    /**
     * Stores the measured litres and the difference from the book stock of that day.
     *
     * @param  array{counted_on: string, quantity: string, description?: ?string}  $data
     */
    public function addCount(FuelStation $tank, array $data, ?string $userId): FuelTankCount
    {
        $this->assertTank($tank);

        return $this->transaction(function () use ($tank, $data, $userId): FuelTankCount {
            $book = $this->stock($tank, Carbon::parse($data['counted_on']))['quantity'];

            /** @var FuelTankCount */
            return $tank->counts()->create([
                ...$data,
                'difference' => round((float) $data['quantity'] - $book, 2),
                'created_by' => $userId,
            ]);
        });
    }

    public function deleteCount(FuelTankCount $count): void
    {
        $count->delete();
    }

    private function assertTank(FuelStation $tank): void
    {
        if (! $tank->isTank()) {
            throw new OperationRejectedException('Dolum ve sayım sadece kendi depomuz için girilir.');
        }
    }
}

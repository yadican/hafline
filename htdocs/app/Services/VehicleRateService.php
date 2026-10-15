<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\Vehicle;
use App\Models\VehicleRate;
use Carbon\CarbonInterface;

/**
 * Rate periods are contiguous: a new rate closes the open period the day before it starts.
 * Machine work tickets keep the rate they were saved with, so changing periods never reprices them.
 */
class VehicleRateService extends Service
{
    public function add(Vehicle $vehicle, string $hourlyRate, CarbonInterface $from): VehicleRate
    {
        return $this->transaction(function () use ($vehicle, $hourlyRate, $from): VehicleRate {
            $current = $vehicle->currentRate()->lockForUpdate()->first();

            if ($current && $from->lte($current->started_on)) {
                throw new OperationRejectedException(sprintf(
                    'Yeni ücretin başlangıcı, geçerli ücretin başlangıcından (%s) sonra olmalı.',
                    $current->started_on->format('d.m.Y'),
                ));
            }

            $current?->update(['ended_on' => $from->copy()->subDay()->toDateString()]);

            /** @var VehicleRate */
            return $vehicle->rates()->create([
                'hourly_rate' => $hourlyRate,
                'started_on' => $from->toDateString(),
            ]);
        });
    }

    /**
     * Only the latest period can be removed; the previous one becomes open-ended again.
     */
    public function undo(Vehicle $vehicle, VehicleRate $rate): void
    {
        $this->transaction(function () use ($vehicle, $rate): void {
            if ($rate->ended_on !== null) {
                throw new OperationRejectedException('Yalnızca son ücret dönemi silinebilir.');
            }

            $previous = $vehicle->rates()
                ->where('started_on', '<', $rate->started_on)
                ->lockForUpdate()
                ->first();

            $rate->delete();
            $previous?->update(['ended_on' => null]);
        });
    }
}

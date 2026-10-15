<?php

namespace Database\Factories;

use App\Enums\PartyRole;
use App\Models\Party;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    private bool $owned = true;

    private ?Party $owner = null;

    private string $ownedFrom = '2020-01-01';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plate' => fake()->unique()->bothify('34 ?? ####'),
            'vehicle_class_id' => VehicleClass::factory(),
            'driver_name' => fake()->name(),
            'active' => true,
        ];
    }

    /**
     * @param  Collection<int, Vehicle>  $instances
     */
    protected function callAfterCreating(Collection $instances, ?Model $parent = null): void
    {
        if ($this->owned) {
            $instances->each(function (Vehicle $vehicle): void {
                $owner = $this->owner ?? Party::factory()->create();
                $owner->assignRole(PartyRole::VehicleOwner);

                $vehicle->ownerships()->create([
                    'party_id' => $owner->id,
                    'started_on' => $this->ownedFrom,
                ]);
            });
        }

        parent::callAfterCreating($instances, $parent);
    }

    public function ownedBy(Party $owner, string $from = '2020-01-01'): static
    {
        $factory = clone $this;
        $factory->owner = $owner;
        $factory->ownedFrom = $from;

        return $factory;
    }

    public function withoutOwner(): static
    {
        $factory = clone $this;
        $factory->owned = false;

        return $factory;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = []): static
    {
        $factory = parent::newInstance($arguments);
        $factory->owned = $this->owned;
        $factory->owner = $this->owner;
        $factory->ownedFrom = $this->ownedFrom;

        return $factory;
    }
}

<?php

namespace Database\Factories;

use App\Models\Warehouse;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => $this->faker->unique()->bothify('WH-####'),
            'name' => $this->faker->word() . ' Warehouse',
            'type' => $this->faker->randomElement(['gros', 'detail', 'pos']),
            'location' => $this->faker->address(),
            'max_capacity' => $this->faker->numberBetween(1000, 10000),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Stock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockFactory extends Factory
{
    protected $model = Stock::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        
        return [
            'tenant_id' => $tenant,
            'product_id' => Product::factory()->for($tenant),
            'warehouse_id' => Warehouse::factory()->for($tenant),
            'quantity' => $this->faker->numberBetween(10, 500),
            'reserved' => 0,
            'available' => $this->faker->numberBetween(10, 500),
            'unit_cost' => $this->faker->numberBetween(1000, 50000),
        ];
    }
}

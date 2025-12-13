<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        
        return [
            'tenant_id' => $tenant,
            'user_id' => User::factory()->for($tenant),
            'reference' => $this->faker->unique()->bothify('SAL-####'),
            'customer_name' => $this->faker->name(),
            'subtotal' => $this->faker->numberBetween(10000, 100000),
            'tax_amount' => 0,
            'total' => $this->faker->numberBetween(10000, 100000),
            'status' => 'completed',
        ];
    }
}

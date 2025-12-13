<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        
        return [
            'tenant_id' => $tenant,
            'user_id' => User::factory()->for($tenant),
            'supplier_id' => Supplier::factory()->for($tenant),
            'reference' => $this->faker->unique()->bothify('ACH-####'),
            'supplier_name' => $this->faker->company(),
            'supplier_phone' => $this->faker->phoneNumber(),
            'supplier_email' => $this->faker->companyEmail(),
            'subtotal' => $this->faker->numberBetween(10000, 100000),
            'tax_amount' => 0,
            'total' => $this->faker->numberBetween(10000, 100000),
            'status' => 'draft',
        ];
    }
}

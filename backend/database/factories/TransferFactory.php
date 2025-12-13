<?php

namespace Database\Factories;

use App\Models\Transfer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();
        
        return [
            'tenant_id' => $tenant,
            'user_id' => User::factory()->for($tenant),
            'reference' => $this->faker->unique()->bothify('TRF-####'),
            'from_warehouse_id' => Warehouse::factory()->for($tenant),
            'to_warehouse_id' => Warehouse::factory()->for($tenant),
            'status' => 'pending',
            'requested_at' => now(),
        ];
    }
}

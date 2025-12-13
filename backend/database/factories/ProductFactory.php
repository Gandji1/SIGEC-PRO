<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $purchase_price = $this->faker->numberBetween(1000, 50000);
        $selling_price = $purchase_price * 1.3;

        return [
            'tenant_id' => Tenant::factory(),
            'code' => $this->faker->unique()->ean13(),
            'name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->word(),
            'purchase_price' => $purchase_price,
            'selling_price' => $selling_price,
            'unit' => 'pcs',
            'min_stock' => 10,
            'max_stock' => 100,
            'barcode' => $this->faker->unique()->ean13(),
            'tax_percent' => 18,
            'status' => 'active',
        ];
    }
}

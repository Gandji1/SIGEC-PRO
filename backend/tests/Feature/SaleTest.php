<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $token;
    private $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Stock::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'available' => 100,
        ]);
    }

    public function test_user_can_create_sale()
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/sales', [
                'customer_name' => 'John Customer',
                'mode' => 'manual',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                        'unit_price' => $this->product->selling_price,
                    ]
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'reference', 'total', 'items']);
    }

    public function test_user_can_complete_sale()
    {
        $sale = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/sales', [
                'customer_name' => 'John Customer',
                'mode' => 'manual',
                'payment_method' => 'cash',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 5,
                        'unit_price' => $this->product->selling_price,
                    ]
                ],
            ])->json();

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson("/api/sales/{$sale['id']}/complete", [
                'amount_paid' => $sale['total'],
                'payment_method' => 'cash',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sales', ['id' => $sale['id'], 'status' => 'completed']);
    }

    public function test_user_can_get_sales_list()
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/sales');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }
}

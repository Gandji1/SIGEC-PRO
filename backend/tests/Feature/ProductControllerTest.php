<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_can_list_products()
    {
        Product::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
    }

    public function test_can_create_product()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/products', [
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'price' => 1500,
            'cost_price' => 1000,
            'category' => 'Test',
            'unit' => 'pcs',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Product');
    }

    public function test_can_update_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Product',
            'price' => 2000,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Product', $product->fresh()->name);
    }

    public function test_can_delete_product()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertStatus(200);
    }

    public function test_cannot_access_other_tenant_products()
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($this->user);

        $response = $this->getJson("/api/products/{$otherProduct->id}");

        $response->assertStatus(404);
    }
}

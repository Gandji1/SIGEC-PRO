<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected User $user1;
    protected User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer deux tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1',
            'status' => 'active',
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2',
            'status' => 'active',
        ]);

        // Créer un utilisateur pour chaque tenant
        $this->user1 = User::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $this->user2 = User::create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
    }

    /** @test */
    public function user_cannot_access_other_tenant_products()
    {
        // Créer un produit pour tenant1
        $product1 = Product::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Product Tenant 1',
            'code' => 'PT1',
            'selling_price' => 100,
        ]);

        // Créer un produit pour tenant2
        $product2 = Product::create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Product Tenant 2',
            'code' => 'PT2',
            'selling_price' => 200,
        ]);

        // User1 ne doit voir que les produits de tenant1
        $this->actingAs($this->user1);
        $products = Product::all();
        
        $this->assertCount(1, $products);
        $this->assertEquals($product1->id, $products->first()->id);
        $this->assertEquals('Product Tenant 1', $products->first()->name);
    }

    /** @test */
    public function user_cannot_access_other_tenant_product_by_id()
    {
        // Créer un produit pour tenant2
        $product2 = Product::create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Product Tenant 2',
            'code' => 'PT2',
            'selling_price' => 200,
        ]);

        // User1 ne doit pas pouvoir accéder au produit de tenant2
        $this->actingAs($this->user1);
        
        $product = Product::find($product2->id);
        $this->assertNull($product);
    }

    /** @test */
    public function product_auto_assigns_tenant_id_on_creation()
    {
        $this->actingAs($this->user1);

        $product = Product::create([
            'name' => 'Auto Tenant Product',
            'code' => 'ATP',
            'selling_price' => 150,
        ]);

        $this->assertEquals($this->tenant1->id, $product->tenant_id);
    }

    /** @test */
    public function api_products_endpoint_returns_only_tenant_products()
    {
        // Créer des produits pour les deux tenants
        Product::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Product T1',
            'code' => 'PT1',
            'selling_price' => 100,
        ]);

        Product::create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Product T2',
            'code' => 'PT2',
            'selling_price' => 200,
        ]);

        // Appel API en tant que user1
        $response = $this->actingAs($this->user1, 'sanctum')
            ->getJson('/api/products');

        $response->assertStatus(200);
        
        // Vérifier qu'on ne voit que les produits de tenant1
        $data = $response->json('data') ?? $response->json();
        $products = is_array($data) ? $data : [];
        
        foreach ($products as $product) {
            $this->assertEquals($this->tenant1->id, $product['tenant_id']);
        }
    }

    /** @test */
    public function superadmin_without_impersonation_sees_no_tenant_data()
    {
        // Créer un superadmin
        $superadmin = User::create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'role' => 'superadmin',
        ]);

        // Créer des produits
        Product::create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Product T1',
            'code' => 'PT1',
            'selling_price' => 100,
        ]);

        // SuperAdmin sans impersonation ne voit aucun produit tenant
        $this->actingAs($superadmin);
        $products = Product::all();
        
        // Le scope fail-closed retourne 0 résultats pour superadmin sans tenant
        $this->assertCount(0, $products);
    }
}

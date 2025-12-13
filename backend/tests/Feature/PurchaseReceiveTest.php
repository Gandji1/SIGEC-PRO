<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Domains\Purchases\Services\PurchaseService;
use Tests\TestCase;

class PurchaseReceiveTest extends TestCase
{
    private Tenant $tenant;
    private User $user;
    private Product $product;
    private PurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer tenant
        $this->tenant = Tenant::factory()->create(['mode_pos' => 'A']);
        
        // Créer warehouse gros
        Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'gros',
        ]);

        // Créer user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        // Créer product
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_price' => 1000,
            'selling_price' => 1500,
        ]);

        $this->purchaseService = new PurchaseService();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_create_purchase()
    {
        $response = $this->postJson('/api/purchases', [
            'supplier_name' => 'Acme Inc',
            'supplier_email' => 'supplier@acme.com',
            'supplier_phone' => '+229 12345678',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 1000,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchases', [
            'tenant_id' => $this->tenant->id,
            'supplier_name' => 'Acme Inc',
            'status' => 'pending',
        ]);
    }

    public function test_purchase_receive_calculates_cmp()
    {
        // Créer achat initial
        $purchase = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'confirmed',
        ]);

        // Ajouter 10 unités à 1000 chacune
        $item = $purchase->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'unit_price' => 1000,
            'quantity_received' => 10,
        ]);

        // Recevoir achat
        $this->purchaseService->receivePurchase($purchase->id);

        // Vérifier stock avec CMP = 1000
        $stock = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', 1)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(10, $stock->quantity);
        $this->assertEquals(1000, $stock->cost_average);
    }

    public function test_cmp_calculation_with_multiple_receives()
    {
        // Recevoir 10 unités à 1000
        $purchase1 = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'confirmed',
        ]);

        $item1 = $purchase1->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'unit_price' => 1000,
            'quantity_received' => 10,
        ]);

        $this->purchaseService->receivePurchase($purchase1->id);

        $stock1 = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', 1)
            ->first();
        $this->assertEquals(1000, $stock1->cost_average);

        // Recevoir 5 unités à 1200
        $purchase2 = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'confirmed',
        ]);

        $item2 = $purchase2->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'unit_price' => 1200,
            'quantity_received' => 5,
        ]);

        $this->purchaseService->receivePurchase($purchase2->id);

        $stock2 = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', 1)
            ->first();

        // CMP = (10 * 1000 + 5 * 1200) / (10 + 5) = 14000 / 15 = 933.33
        $expectedCMP = (10 * 1000 + 5 * 1200) / (10 + 5);
        $this->assertEquals(15, $stock2->quantity);
        $this->assertEqualsWithDelta($expectedCMP, $stock2->cost_average, 0.01);
    }

    public function test_purchase_creates_stock_movement()
    {
        $purchase = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'confirmed',
        ]);

        $item = $purchase->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'unit_price' => 1000,
            'quantity_received' => 10,
        ]);

        $this->purchaseService->receivePurchase($purchase->id);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 10,
            'unit_cost' => 1000,
        ]);
    }

    public function test_can_confirm_purchase()
    {
        $purchase = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->purchaseService->confirmPurchase($purchase->id);

        $purchase->refresh();
        $this->assertEquals('confirmed', $purchase->status);
        $this->assertNotNull($purchase->confirmed_at);
    }

    public function test_can_cancel_pending_purchase()
    {
        $purchase = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->purchaseService->cancelPurchase($purchase->id);

        $purchase->refresh();
        $this->assertEquals('cancelled', $purchase->status);
    }

    public function test_cannot_cancel_received_purchase()
    {
        $purchase = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'received',
        ]);

        $this->expectException(\Exception::class);
        $this->purchaseService->cancelPurchase($purchase->id);
    }
}

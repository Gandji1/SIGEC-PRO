<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseReceiveFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private Supplier $supplier;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create(['name' => 'Test Shop']);

        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@shop.local',
        ]);

        // Get token
        $response = $this->postJson('/api/login', [
            'email' => 'test@shop.local',
            'password' => 'password',
        ]);

        $this->token = $response->json('token');

        // Create product
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create supplier
        $this->supplier = Supplier::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * Test: Create tenant (Option A) → create supplier → create PO → receive PO → stock_gros updated with CMP
     */
    public function test_purchase_receive_flow_with_cmp()
    {
        // Step 1: Create Purchase Order
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/purchases', [
                'supplier_id' => $this->supplier->id,
                'supplier_name' => $this->supplier->name,
                'payment_method' => 'transfer',
                'expected_date' => now()->addDays(5)->toDateString(),
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 100,
                        'unit_price' => 10.00,
                    ],
                ],
            ]);

        $this->assertResponseOk($response);
        $purchase_id = $response->json('data.id');

        // Step 2: Verify PO created with status 'pending'
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/purchases/{$purchase_id}");
        
        $this->assertEquals('pending', $response->json('data.status'));

        // Step 3: Confirm PO
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/purchases/{$purchase_id}/confirm");

        $this->assertResponseOk($response);
        $this->assertEquals('confirmed', $response->json('data.status'));

        // Step 4: Receive PO (should update stock with CMP)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/purchases/{$purchase_id}/receive");

        $this->assertResponseOk($response);
        $this->assertEquals('received', $response->json('purchase.status'));

        // Step 5: Verify stock updated with CMP
        $stock = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(100, $stock->quantity);
        $this->assertEquals(10.00, $stock->cost_average); // CMP = 10.00 initially
    }

    /**
     * Test: CMP calculation on subsequent receipts
     */
    public function test_cmp_calculation_on_second_receipt()
    {
        // First receipt: 100 @ 10 = CMP 10
        $purchase1 = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'confirmed',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase1->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'unit_price' => 10.00,
        ]);

        // Manual stock update for first receipt
        Stock::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'warehouse_id' => 1,
            ],
            [
                'quantity' => 100,
                'cost_average' => 10.00,
                'unit_cost' => 10.00,
            ]
        );

        // Second receipt: 50 @ 15 = CMP = (100*10 + 50*15) / 150 = 11.67
        $purchase2 = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'confirmed',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase2->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'quantity_received' => 50,
            'unit_price' => 15.00,
        ]);

        // Simulate receive via controller
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/purchases/{$purchase2->id}/receive");

        $this->assertResponseOk($response);

        // Verify CMP recalculation
        $stock = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->first();

        $expected_cmp = (100 * 10 + 50 * 15) / 150;
        $this->assertEquals(150, $stock->quantity);
        $this->assertEqualsWithDelta($expected_cmp, $stock->cost_average, 0.01);
    }

    private function assertResponseOk($response)
    {
        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            "Expected 200 or 201 but got {$response->status()}: " . json_encode($response->json())
        );
    }
}

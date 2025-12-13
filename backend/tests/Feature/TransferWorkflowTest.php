<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Stock;
use App\Models\Transfer;
use App\Domains\Transfers\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Warehouse $warehouse_gros;
    private Warehouse $warehouse_detail;
    private Product $product;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $this->token = $response->json('token');

        // Create warehouses
        $this->warehouse_gros = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bulk Warehouse',
        ]);

        $this->warehouse_detail = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Detail Warehouse',
        ]);

        // Create product
        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Create stock in gros
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse_gros->id,
            'quantity' => 1000,
            'cost_average' => 10.00,
            'unit_cost' => 10.00,
            'reserved' => 0,
            'available' => 1000,
        ]);
    }

    /**
     * Test: Transfer Request → Approve → Execute workflow
     */
    public function test_transfer_workflow_complete()
    {
        // Step 1: Create transfer request
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/transfers', [
                'from_warehouse_id' => $this->warehouse_gros->id,
                'to_warehouse_id' => $this->warehouse_detail->id,
                'description' => 'Move 100 units to detail',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 100,
                    ],
                ],
            ]);

        $this->assertEquals(201, $response->status());
        $transfer_id = $response->json('id');

        // Step 2: Approve transfer
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/transfers/{$transfer_id}/approve");

        $this->assertEquals(200, $response->status());

        // Step 3: Execute transfer
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/transfers/{$transfer_id}/execute");

        $this->assertEquals(200, $response->status());

        // Step 4: Verify stock moved
        $source_stock = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse_gros->id)
            ->first();

        $dest_stock = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse_detail->id)
            ->first();

        // Verify quantities
        $this->assertEquals(900, $source_stock->quantity); // 1000 - 100
        $this->assertEquals(100, $dest_stock->quantity);   // 0 + 100
        $this->assertEquals(10.00, $dest_stock->cost_average); // CMP maintained
    }

    /**
     * Test: Cancel transfer
     */
    public function test_cancel_transfer()
    {
        $transfer = Transfer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouse_gros->id,
            'to_warehouse_id' => $this->warehouse_detail->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/transfers/{$transfer->id}/cancel");

        $this->assertEquals(200, $response->status());
        $this->assertEquals('cancelled', $response->json('status'));
    }

    /**
     * Test: Insufficient stock in source
     */
    public function test_transfer_insufficient_stock()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/transfers', [
                'from_warehouse_id' => $this->warehouse_gros->id,
                'to_warehouse_id' => $this->warehouse_detail->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2000, // More than available
                    ],
                ],
            ]);

        $this->assertEquals(400, $response->status());
        $this->assertStringContainsString('Insufficient stock', $response->json('error'));
    }

    /**
     * Test: Get pending transfers
     */
    public function test_get_pending_transfers()
    {
        Transfer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/transfers/pending');

        $this->assertEquals(200, $response->status());
        $this->assertGreaterThanOrEqual(3, $response->json('count'));
    }
}

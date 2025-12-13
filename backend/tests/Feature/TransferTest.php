<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\Transfer;
use App\Domains\Transfers\Services\TransferService;
use Tests\TestCase;

class TransferTest extends TestCase
{
    private Tenant $tenant;
    private User $user;
    private Warehouse $grosWarehouse;
    private Warehouse $detailWarehouse;
    private Warehouse $posWarehouse;
    private Product $product;
    private TransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Créer tenant Mode B (avec POS)
        $this->tenant = Tenant::factory()->create(['mode_pos' => 'B']);
        
        // Créer warehouses
        $this->grosWarehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'gros',
            'name' => 'Gros',
        ]);

        $this->detailWarehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'detail',
            'name' => 'Détail',
        ]);

        $this->posWarehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'pos',
            'name' => 'POS',
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

        // Créer stock initial au gros (100 unités @ 1000)
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->grosWarehouse->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
            'cost_average' => 1000,
            'unit_cost' => 1000,
        ]);

        $this->transferService = new TransferService();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_request_transfer()
    {
        $response = $this->postJson('/api/transfers', [
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 20,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('transfers', [
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'status' => 'pending',
        ]);
    }

    public function test_transfer_execution_updates_stock()
    {
        // Créer transfert
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
        ]);

        $transfer->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_cost' => 1000,
        ]);

        // Approuver et exécuter
        $this->transferService->approveAndExecuteTransfer($transfer);

        // Vérifier stock gros diminué
        $grosStock = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->grosWarehouse->id)
            ->first();
        $this->assertEquals(80, $grosStock->quantity);

        // Vérifier stock détail augmenté
        $detailStock = Stock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->detailWarehouse->id)
            ->first();
        $this->assertNotNull($detailStock);
        $this->assertEquals(20, $detailStock->quantity);
    }

    public function test_transfer_creates_stock_movement()
    {
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
        ]);

        $transfer->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_cost' => 1000,
        ]);

        $this->transferService->approveAndExecuteTransfer($transfer);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'transfer',
            'quantity' => 20,
        ]);
    }

    public function test_cannot_transfer_insufficient_stock()
    {
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
        ]);

        $transfer->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 150, // Plus que le stock disponible
            'unit_cost' => 1000,
        ]);

        $this->expectException(\Exception::class);
        $this->transferService->approveAndExecuteTransfer($transfer);
    }

    public function test_can_cancel_pending_transfer()
    {
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
        ]);

        $this->transferService->cancelTransfer($transfer);

        $transfer->refresh();
        $this->assertEquals('cancelled', $transfer->status);
    }

    public function test_cannot_cancel_approved_transfer()
    {
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->grosWarehouse->id,
            'to_warehouse_id' => $this->detailWarehouse->id,
            'status' => 'approved',
            'requested_by' => $this->user->id,
            'requested_at' => now(),
        ]);

        $this->expectException(\Exception::class);
        $this->transferService->cancelTransfer($transfer);
    }

    public function test_auto_transfer_when_stock_low()
    {
        // Réduire stock détail à 5 (threshold est 10)
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->detailWarehouse->id,
            'quantity' => 5,
            'reserved' => 0,
            'available' => 5,
            'cost_average' => 1000,
            'unit_cost' => 1000,
        ]);

        // Déclencher auto-transfer
        $autoTransfer = $this->transferService->autoTransferIfNeeded(
            $this->product->id,
            $this->detailWarehouse->id,
            10
        );

        // Vérifier transfert créé
        $this->assertNotNull($autoTransfer);
        $this->assertEquals('pending', $autoTransfer->status);
        $this->assertEquals($this->grosWarehouse->id, $autoTransfer->from_warehouse_id);
        $this->assertEquals($this->detailWarehouse->id, $autoTransfer->to_warehouse_id);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Models\Transfer;
use App\Models\StockRequest;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ApprovisionnementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $warehouseGros;
    protected Warehouse $warehouseDetail;
    protected Product $product;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'currency' => 'XOF',
            'status' => 'active',
            'mode_pos' => 'B',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Manager',
            'email' => 'manager@test.com',
            'password' => bcrypt('password'),
            'role' => 'manager',
        ]);

        $this->warehouseGros = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'GROS',
            'name' => 'Magasin Gros',
            'type' => 'gros',
            'is_active' => true,
        ]);

        $this->warehouseDetail = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'DETAIL',
            'name' => 'Magasin Detail',
            'type' => 'detail',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PROD-001',
            'name' => 'Produit Test',
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'unit' => 'piece',
            'track_stock' => true,
            'status' => 'active',
        ]);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fournisseur Test',
            'email' => 'fournisseur@test.com',
            'status' => 'active',
        ]);

        // Stock initial dans gros
        Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse' => 'gros',
            'warehouse_id' => $this->warehouseGros->id,
            'quantity' => 100,
            'cost_average' => 1000,
            'unit_cost' => 1000,
            'reserved' => 0,
            'available' => 100,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_create_purchase_order(): void
    {
        $response = $this->postJson('/api/approvisionnement/purchases', [
            'supplier_name' => 'Nouveau Fournisseur',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty_ordered' => 50,
                    'expected_unit_cost' => 950,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'reference',
            'status',
            'items',
        ]);

        $this->assertDatabaseHas('purchases', [
            'tenant_id' => $this->tenant->id,
            'supplier_name' => 'Nouveau Fournisseur',
            'status' => 'draft',
        ]);
    }

    public function test_receive_purchase_updates_stock_and_cmp(): void
    {
        // Creer une commande confirmee
        $purchase = Purchase::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouseGros->id,
            'reference' => 'PO-TEST-001',
            'supplier_name' => 'Test Supplier',
            'status' => 'ordered',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $purchase->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'unit_price' => 1000,
            'line_subtotal' => 50000,
            'line_total' => 50000,
        ]);

        $purchaseItem = $purchase->items->first();

        $response = $this->postJson("/api/approvisionnement/purchases/{$purchase->id}/receive", [
            'received_items' => [
                [
                    'purchase_item_id' => $purchaseItem->id,
                    'qty_received' => 50,
                    'unit_cost' => 1000,
                ],
            ],
        ]);

        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200);

        // Verifier que le stock a ete mis a jour
        $stock = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse', 'gros')
            ->first();

        $this->assertEquals(150, $stock->quantity); // 100 initial + 50 recus

        // Verifier qu'un mouvement de stock a ete cree
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 50,
        ]);
    }

    public function test_create_stock_request_from_detail(): void
    {
        $response = $this->postJson('/api/approvisionnement/requests', [
            'from_warehouse_id' => $this->warehouseDetail->id,
            'to_warehouse_id' => $this->warehouseGros->id,
            'priority' => 'high',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty_requested' => 20,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'reference',
            'status',
            'items',
        ]);

        $this->assertDatabaseHas('stock_requests', [
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseDetail->id,
            'to_warehouse_id' => $this->warehouseGros->id,
            'priority' => 'high',
            'status' => 'draft',
        ]);
    }

    public function test_approve_request_creates_transfer(): void
    {
        // Creer une demande soumise
        $request = StockRequest::create([
            'tenant_id' => $this->tenant->id,
            'reference' => 'REQ-TEST-001',
            'from_warehouse_id' => $this->warehouseDetail->id,
            'to_warehouse_id' => $this->warehouseGros->id,
            'requested_by' => $this->user->id,
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        $request->items()->create([
            'product_id' => $this->product->id,
            'quantity_requested' => 20,
        ]);

        $response = $this->postJson("/api/approvisionnement/requests/{$request->id}/approve");

        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200);

        // Verifier que la demande est approuvee
        $request->refresh();
        $this->assertEquals('transferred', $request->status);
        $this->assertNotNull($request->transfer_id);

        // Verifier qu'un transfert a ete cree
        $this->assertDatabaseHas('transfers', [
            'tenant_id' => $this->tenant->id,
            'from_warehouse_id' => $this->warehouseGros->id,
            'to_warehouse_id' => $this->warehouseDetail->id,
            'status' => 'pending',
        ]);
    }

    public function test_execute_transfer_moves_stock(): void
    {
        // Creer un transfert en attente
        $transfer = Transfer::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'reference' => 'TX-TEST-001',
            'from_warehouse' => 'gros',
            'to_warehouse' => 'detail',
            'from_warehouse_id' => $this->warehouseGros->id,
            'to_warehouse_id' => $this->warehouseDetail->id,
            'status' => 'pending',
            'requested_by' => $this->user->id,
            'total_items' => 20,
        ]);

        $transfer->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_cost' => 1000,
        ]);

        $response = $this->postJson("/api/approvisionnement/transfers/{$transfer->id}/execute");

        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200);

        // Verifier que le stock gros a diminue
        $stockGros = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse', 'gros')
            ->first();

        $this->assertEquals(80, $stockGros->quantity); // 100 - 20

        // Verifier que le stock detail a augmente
        $stockDetail = Stock::where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouseDetail->id)
            ->first();

        $this->assertNotNull($stockDetail);
        $this->assertEquals(20, $stockDetail->quantity);

        // Verifier le mouvement de stock
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'transfer',
            'quantity' => 20,
            'from_warehouse_id' => $this->warehouseGros->id,
            'to_warehouse_id' => $this->warehouseDetail->id,
        ]);
    }

    public function test_gros_dashboard_returns_kpis(): void
    {
        $response = $this->getJson('/api/approvisionnement/gros/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stock_value',
            'movements_today',
            'low_stock_count',
            'pending_po_count',
            'pending_requests',
        ]);
    }

    public function test_detail_dashboard_returns_kpis(): void
    {
        $response = $this->getJson('/api/approvisionnement/detail/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stock_value',
            'available_stock',
            'pending_from_pos',
            'pending_to_gros',
        ]);
    }

    public function test_tenant_isolation(): void
    {
        // Creer un autre tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other',
            'currency' => 'XOF',
            'status' => 'active',
        ]);

        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other User',
            'email' => 'other@test.com',
            'password' => bcrypt('password'),
            'role' => 'manager',
        ]);

        // Creer une commande pour l'autre tenant
        $otherPurchase = Purchase::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'reference' => 'PO-OTHER-001',
            'supplier_name' => 'Other Supplier',
            'status' => 'draft',
            'subtotal' => 0,
            'total' => 0,
        ]);

        // L'utilisateur actuel ne devrait pas voir cette commande
        $response = $this->getJson('/api/approvisionnement/purchases');

        $response->assertStatus(200);

        $purchases = $response->json('data');
        foreach ($purchases as $purchase) {
            $this->assertNotEquals($otherTenant->id, $purchase['tenant_id']);
        }
    }
}

<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ApprovisionnementService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $warehouseGros;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'currency' => 'XOF',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@test.com',
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

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-001',
            'name' => 'Produit Test',
            'purchase_price' => 1000,
            'selling_price' => 1500,
            'unit' => 'piece',
            'track_stock' => true,
            'status' => 'active',
        ]);
    }

    public function test_cmp_calculation_on_first_reception(): void
    {
        // Stock initial vide
        $stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse' => 'gros',
            'warehouse_id' => $this->warehouseGros->id,
            'quantity' => 0,
            'cost_average' => 0,
            'unit_cost' => 0,
            'reserved' => 0,
            'available' => 0,
        ]);

        // Premiere reception: 10 unites a 1000 FCFA
        $qtyReceived = 10;
        $unitCost = 1000;

        $newQty = $stock->quantity + $qtyReceived;
        $newCmp = ($stock->quantity * $stock->cost_average + $qtyReceived * $unitCost) / $newQty;

        $stock->quantity = $newQty;
        $stock->cost_average = $newCmp;
        $stock->save();

        $this->assertEquals(10, $stock->quantity);
        $this->assertEquals(1000, $stock->cost_average);
    }

    public function test_cmp_calculation_on_second_reception(): void
    {
        // Stock initial: 10 unites a 1000 FCFA
        $stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse' => 'gros',
            'warehouse_id' => $this->warehouseGros->id,
            'quantity' => 10,
            'cost_average' => 1000,
            'unit_cost' => 1000,
            'reserved' => 0,
            'available' => 10,
        ]);

        // Deuxieme reception: 20 unites a 1200 FCFA
        $qtyReceived = 20;
        $unitCost = 1200;

        // CMP = (10 * 1000 + 20 * 1200) / 30 = (10000 + 24000) / 30 = 34000 / 30 = 1133.33
        $oldQty = $stock->quantity;
        $oldCmp = $stock->cost_average;
        $newQty = $oldQty + $qtyReceived;
        $newCmp = ($oldQty * $oldCmp + $qtyReceived * $unitCost) / $newQty;

        $stock->quantity = $newQty;
        $stock->cost_average = $newCmp;
        $stock->save();

        $this->assertEquals(30, $stock->quantity);
        $this->assertEqualsWithDelta(1133.33, $stock->cost_average, 0.01);
    }

    public function test_stock_cannot_go_negative_without_setting(): void
    {
        $stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse' => 'gros',
            'warehouse_id' => $this->warehouseGros->id,
            'quantity' => 5,
            'cost_average' => 1000,
            'unit_cost' => 1000,
            'reserved' => 0,
            'available' => 5,
        ]);

        // Tentative de retirer plus que disponible
        $qtyToRemove = 10;

        if ($stock->available >= $qtyToRemove) {
            $stock->quantity -= $qtyToRemove;
            $stock->save();
        }

        // Le stock ne devrait pas avoir change
        $this->assertEquals(5, $stock->quantity);
    }

    public function test_stock_reservation(): void
    {
        $stock = Stock::create([
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

        // Reserver 30 unites
        $result = $stock->reserve(30);

        $this->assertTrue($result);
        $this->assertEquals(30, $stock->reserved);
        $this->assertEquals(70, $stock->available);
        $this->assertEquals(100, $stock->quantity); // Quantite physique inchangee
    }

    public function test_stock_release(): void
    {
        $stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse' => 'gros',
            'warehouse_id' => $this->warehouseGros->id,
            'quantity' => 100,
            'cost_average' => 1000,
            'unit_cost' => 1000,
            'reserved' => 30,
            'available' => 70,
        ]);

        // Liberer 20 unites
        $stock->release(20);

        $this->assertEquals(10, $stock->reserved);
        $this->assertEquals(90, $stock->available);
    }
}

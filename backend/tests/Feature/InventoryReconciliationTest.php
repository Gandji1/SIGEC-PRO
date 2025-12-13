<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Stock;
use App\Models\InventoryCount;
use App\Domains\Inventory\Services\InventoryReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Warehouse $warehouse;
    private Product $product;
    private Stock $stock;
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

        // Create warehouse and product
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create stock with 100 units at $10 cost average
        $this->stock = Stock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'cost_average' => 10.00,
            'unit_cost' => 10.00,
            'available' => 100,
            'reserved' => 0,
        ]);
    }

    /**
     * Test: Start physical inventory count
     */
    public function test_start_inventory_count()
    {
        $service = new InventoryReconciliationService($this->tenant);

        $count = $service->startInventoryCount(
            $this->warehouse->id,
            'periodic'
        );

        $this->assertInstanceOf(InventoryCount::class, $count);
        $this->assertEquals('in_progress', $count->status);
        $this->assertEquals($this->warehouse->id, $count->warehouse_id);
    }

    /**
     * Test: Record surplus (physical > expected)
     */
    public function test_record_surplus()
    {
        $service = new InventoryReconciliationService($this->tenant);
        $count = $service->startInventoryCount($this->warehouse->id, 'periodic');

        // Record 120 units (surplus of 20)
        $item = $service->recordCountItem($count, $this->product->id, 120);

        $this->assertEquals(100, $item->expected_quantity);
        $this->assertEquals(120, $item->physical_quantity);
        $this->assertEquals(20, $item->variance);
    }

    /**
     * Test: Record shortage (physical < expected)
     */
    public function test_record_shortage()
    {
        $service = new InventoryReconciliationService($this->tenant);
        $count = $service->startInventoryCount($this->warehouse->id, 'periodic');

        // Record 80 units (shortage of 20)
        $item = $service->recordCountItem($count, $this->product->id, 80);

        $this->assertEquals(100, $item->expected_quantity);
        $this->assertEquals(80, $item->physical_quantity);
        $this->assertEquals(-20, $item->variance);
    }

    /**
     * Test: Complete count and generate GL entries
     */
    public function test_complete_count_with_gl_entries()
    {
        $service = new InventoryReconciliationService($this->tenant);
        $count = $service->startInventoryCount($this->warehouse->id, 'periodic');

        // Record shortage
        $service->recordCountItem($count, $this->product->id, 95);

        // Complete count
        $result = $service->completeInventoryCount($count);

        // Verify GL entries created (2 per variance: debit/credit)
        $this->assertEquals(2, $result['gl_entries_created']);
        $this->assertEquals(1, $result['items_with_variance']);
        
        // Verify variance value (5 units × $10 = $50)
        $this->assertEquals(50, $result['total_variance_value']);

        // Verify stock updated
        $this->stock->refresh();
        $this->assertEquals(95, $this->stock->quantity);
    }

    /**
     * Test: Get inventory count summary
     */
    public function test_get_count_summary()
    {
        $service = new InventoryReconciliationService($this->tenant);
        $count = $service->startInventoryCount($this->warehouse->id, 'periodic');

        $service->recordCountItem($count, $this->product->id, 105);
        $service->completeInventoryCount($count);

        $summary = $service->getCountSummary($count);

        $this->assertEquals(1, $summary['items_count']);
        $this->assertEquals(1, $summary['items_with_variance']);
        $this->assertEquals(100, $summary['total_expected_quantity']);
        $this->assertEquals(105, $summary['total_physical_quantity']);
        $this->assertEquals(5, $summary['total_variance']);
    }

    /**
     * Test: Variance analysis
     */
    public function test_variance_analysis()
    {
        $service = new InventoryReconciliationService($this->tenant);
        $count = $service->startInventoryCount($this->warehouse->id, 'periodic');

        $service->recordCountItem($count, $this->product->id, 90);

        $analysis = $service->getVarianceAnalysis($count);

        $this->assertCount(1, $analysis);
        $this->assertEquals('shortage', $analysis[0]['type']);
        $this->assertEquals(-10, $analysis[0]['variance']);
        $this->assertEquals(100.00, $analysis[0]['variance_value']);
    }

    /**
     * Test: Cancel inventory count
     */
    public function test_cancel_inventory_count()
    {
        $service = new InventoryReconciliationService($this->tenant);
        $count = $service->startInventoryCount($this->warehouse->id, 'periodic');

        $service->cancelInventoryCount($count);

        $count->refresh();
        $this->assertEquals('cancelled', $count->status);
    }

    /**
     * Test: API endpoint - start count
     */
    public function test_api_start_count()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/inventory-counts/start', [
                'warehouse_id' => $this->warehouse->id,
                'reason' => 'periodic',
            ]);

        $this->assertEquals(201, $response->status());
        $this->assertEquals('started', $response->json('status'));
    }
}

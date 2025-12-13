<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\AccountingEntry;
use App\Domains\Accounting\Services\AutoPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GLAutoPostingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
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

        $this->product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_price' => 10.00,
            'selling_price' => 15.00,
        ]);
    }

    /**
     * Test: Purchase Receipt creates GL entries
     */
    public function test_purchase_receive_creates_gl_entries()
    {
        // Create and receive purchase
        $purchase = Purchase::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total' => 1000,
            'status' => 'confirmed',
        ]);

        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'quantity_received' => 100,
            'unit_price' => 10.00,
            'line_total' => 1000,
        ]);

        // Receive via API
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/purchases/{$purchase->id}/receive");

        $this->assertResponseOk($response);

        // Verify GL entries created
        $entries = AccountingEntry::where('tenant_id', $this->tenant->id)
            ->where('source_id', $purchase->id)
            ->get();

        $this->assertCount(2, $entries); // Debit + Credit

        // Check Debit (Inventory)
        $debit = $entries->firstWhere('type', 'debit');
        $this->assertEquals(1000, $debit->amount);
        $this->assertEquals('posted', $debit->status);

        // Check Credit (Payable)
        $credit = $entries->firstWhere('type', 'credit');
        $this->assertEquals(1000, $credit->amount);
        $this->assertEquals('posted', $credit->status);

        // Verify Trial Balance balances
        $autoPosting = new AutoPostingService($this->tenant->id);
        $this->assertTrue($autoPosting->verifyBalance());
    }

    /**
     * Test: Sale Completion creates GL entries with COGS
     */
    public function test_sale_complete_creates_gl_entries_with_cogs()
    {
        // Create sale
        $sale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
            'total' => 300,
            'tax_amount' => 0,
            'payment_method' => 'cash',
        ]);

        $sale->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 15.00,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'line_total' => 300,
        ]);

        // Complete sale
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/sales/{$sale->id}/complete", [
                'amount_paid' => 300,
                'payment_method' => 'cash',
            ]);

        $this->assertResponseOk($response);

        // Verify GL entries
        $entries = AccountingEntry::where('tenant_id', $this->tenant->id)
            ->where('source_id', $sale->id)
            ->get();

        // Should have: Cash debit, COGS debit, Revenue credit, Inventory credit
        $this->assertGreaterThanOrEqual(3, $entries->count());

        // Verify trial balance
        $autoPosting = new AutoPostingService($this->tenant->id);
        $this->assertTrue($autoPosting->verifyBalance());
    }

    /**
     * Test: Expense creates GL entries
     */
    public function test_expense_creates_gl_entries()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/expenses', [
                'category' => 'transport',
                'description' => 'Fuel delivery',
                'amount' => 50000,
                'date' => now()->toDateString(),
                'payment_method' => 'cash',
            ]);

        $this->assertResponseOk($response);

        // Verify GL entries created
        $entries = AccountingEntry::where('tenant_id', $this->tenant->id)
            ->where('category', 'expenses')
            ->get();

        $this->assertCount(2, $entries); // Expense debit + Cash credit

        // Verify trial balance
        $autoPosting = new AutoPostingService($this->tenant->id);
        $this->assertTrue($autoPosting->verifyBalance());
    }

    private function assertResponseOk($response)
    {
        $this->assertTrue(
            in_array($response->status(), [200, 201]),
            "Expected 200 or 201 but got {$response->status()}"
        );
    }
}

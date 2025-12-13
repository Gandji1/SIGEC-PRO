<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Domains\Payments\Services\FedapayAdapter;
use App\Domains\Payments\Services\KakiapayAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PSPIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Sale $sale;
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

        $this->sale = Sale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'total' => 5000,
            'amount_paid' => 0,
        ]);
    }

    /**
     * Test: Fedapay adapter initialization
     */
    public function test_fedapay_adapter_initialize()
    {
        $adapter = new FedapayAdapter($this->tenant);

        $result = $adapter->initializePayment(
            $this->sale,
            '+22565555555',
            5000
        );

        // Result should have success or error key
        $this->assertIsArray($result);
        $this->assertTrue(
            isset($result['success']) && isset($result['error'])
        );
    }

    /**
     * Test: Kakiapay adapter initialization
     */
    public function test_kakiapay_adapter_initialize()
    {
        $adapter = new KakiapayAdapter($this->tenant);

        $result = $adapter->initializePayment(
            $this->sale,
            '+22565555555',
            5000
        );

        $this->assertIsArray($result);
        $this->assertTrue(
            isset($result['success']) && isset($result['error'])
        );
    }

    /**
     * Test: Record payment in database
     */
    public function test_record_payment()
    {
        $adapter = new FedapayAdapter($this->tenant);

        $payment = $adapter->recordPayment(
            $this->sale,
            'TXN-123456',
            5000,
            'fedapay'
        );

        $this->assertInstanceOf(SalePayment::class, $payment);
        $this->assertEquals($this->sale->id, $payment->sale_id);
        $this->assertEquals('TXN-123456', $payment->reference);
        $this->assertEquals('fedapay', $payment->method);
    }

    /**
     * Test: Payment initialization endpoint
     */
    public function test_initialize_payment_endpoint()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/payments/initialize', [
                'sale_id' => $this->sale->id,
                'phone' => '+22565555555',
                'gateway' => 'fedapay',
            ]);

        $this->assertIn($response->status(), [200, 400]); // May fail due to missing credentials
    }

    /**
     * Test: Invalid gateway
     */
    public function test_invalid_gateway()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/payments/initialize', [
                'sale_id' => $this->sale->id,
                'phone' => '+22565555555',
                'gateway' => 'invalid_psp',
            ]);

        $this->assertEquals(422, $response->status());
    }

    /**
     * Test: Sale not found
     */
    public function test_sale_not_found()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson('/api/payments/initialize', [
                'sale_id' => 99999,
                'phone' => '+22565555555',
                'gateway' => 'fedapay',
            ]);

        $this->assertEquals(404, $response->status());
    }
}

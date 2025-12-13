<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    public function test_can_list_customers()
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
    }

    public function test_can_create_customer()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+229 97 00 00 00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'John Doe');
    }

    public function test_can_update_customer()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'Jane Doe',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Jane Doe', $customer->fresh()->name);
    }

    public function test_can_delete_customer()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);

        $response = $this->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(200);
    }
}

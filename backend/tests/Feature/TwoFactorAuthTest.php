<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use DatabaseTransactions;

    protected $tenant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'status' => 'active',
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
            'status' => 'active',
            'two_factor_enabled' => false,
        ]);
    }

    public function test_user_can_enable_2fa()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/2fa/enable');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'secret',
                'qr_url',
                'message',
            ]);

        $this->assertNotNull($this->user->fresh()->two_factor_secret);
    }

    public function test_user_cannot_enable_2fa_twice()
    {
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString('TESTSECRET123456'),
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/2fa/enable');

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_user_can_get_2fa_status()
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/2fa/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'two_factor_enabled' => false,
            ]);
    }

    public function test_login_returns_requires_2fa_when_enabled()
    {
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString('TESTSECRET123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires_2fa' => true,
            ])
            ->assertJsonStructure(['user_id']);
    }

    public function test_login_returns_token_when_2fa_disabled()
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires_2fa' => false,
            ])
            ->assertJsonStructure(['token', 'user', 'tenant']);
    }

    public function test_user_can_disable_2fa_with_correct_password()
    {
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString('TESTSECRET123456'),
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/2fa/disable', [
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFalse($this->user->fresh()->two_factor_enabled);
    }

    public function test_user_cannot_disable_2fa_with_wrong_password()
    {
        $this->user->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString('TESTSECRET123456'),
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/2fa/disable', [
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertTrue($this->user->fresh()->two_factor_enabled);
    }
}

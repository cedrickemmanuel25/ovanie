<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_web_form_accepts_client_account(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'status' => 'active',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('client.dashboard', absolute: false));
    }

    public function test_historical_web_form_accepts_vendor_account(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'status' => 'active',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('vendor.dashboard', absolute: false));
    }

    public function test_mobile_client_portal_rejects_vendor_account(): void
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'status' => 'active',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Password123!',
            'portal' => 'client',
            'device_name' => 'test-client',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Ce compte est un compte vendeur. Utilisez l’espace vendeur OVANIE.');
    }

    public function test_mobile_vendor_portal_rejects_client_account(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'status' => 'active',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/login', [
            'identifier' => $user->email,
            'password' => 'Password123!',
            'portal' => 'vendor',
            'device_name' => 'test-vendor',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Ce compte est un compte client. Utilisez l’espace client OVANIE.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\ProUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProAuthenticationSeparationTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
    }

    public function test_same_email_can_exist_on_ovanie_and_ovanie_pro(): void
    {
        User::query()->create([
            'first_name' => 'Main',
            'last_name' => 'User',
            'name' => 'Main User',
            'email' => 'same@example.com',
            'password' => Hash::make('MainPassword123!'),
            'role' => 'client',
            'status' => 'active',
        ]);

        $response = $this->post($this->proHost . '/compte/inscription/simple', [
            'first_name' => 'Pro',
            'last_name' => 'User',
            'residence_country' => 'Côte d’Ivoire',
            'preferred_language' => 'fr',
            'email' => 'same@example.com',
            'phone_country' => '+225',
            'phone' => '0102030405',
            'password' => 'ProPassword123!',
            'password_confirmation' => 'ProPassword123!',
            'terms' => '1',
            'purpose' => 'requester',
        ]);

        $response->assertRedirect(route('pro.account'));
        $this->assertDatabaseHas('pro_users', [
            'email' => 'same@example.com',
            'account_type' => 'simple',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'same@example.com']);
    }

    public function test_ovanie_credentials_do_not_authenticate_on_ovanie_pro(): void
    {
        User::query()->create([
            'first_name' => 'Main',
            'last_name' => 'User',
            'name' => 'Main User',
            'email' => 'client@example.com',
            'password' => Hash::make('MainPassword123!'),
            'role' => 'client',
            'status' => 'active',
        ]);

        $response = $this->from($this->proHost . '/compte/connexion')->post($this->proHost . '/compte/connexion', [
            'identifier' => 'client@example.com',
            'password' => 'MainPassword123!',
        ]);

        $response->assertRedirect($this->proHost . '/compte/connexion');
        $response->assertSessionHasErrors('identifier');
        $this->assertGuest('pro');
    }

    public function test_pro_credentials_authenticate_only_the_pro_guard(): void
    {
        $proUser = ProUser::query()->create([
            'first_name' => 'Awa',
            'last_name' => 'Pro',
            'name' => 'Awa Pro',
            'email' => 'awa.pro@example.com',
            'password' => 'ProPassword123!',
            'account_type' => 'pro',
            'status' => 'active',
            'professional_status' => 'draft',
        ]);

        $response = $this->post($this->proHost . '/compte/connexion', [
            'identifier' => 'awa.pro@example.com',
            'password' => 'ProPassword123!',
        ]);

        $response->assertRedirect(route('pro.account'));
        $this->assertAuthenticatedAs($proUser, 'pro');
        $this->assertGuest('web');
    }

    public function test_main_ovanie_session_does_not_grant_access_to_pro_account(): void
    {
        $mainUser = User::query()->create([
            'first_name' => 'Client',
            'last_name' => 'OVANIE',
            'name' => 'Client OVANIE',
            'email' => 'main-only@example.com',
            'password' => Hash::make('MainPassword123!'),
            'role' => 'client',
            'status' => 'active',
        ]);

        $response = $this->actingAs($mainUser, 'web')
            ->get($this->proHost . '/compte/mon-espace');

        $response->assertRedirect(route('pro.login'));
    }

    public function test_suspended_pro_account_cannot_login(): void
    {
        ProUser::query()->create([
            'first_name' => 'Compte',
            'last_name' => 'Suspendu',
            'name' => 'Compte Suspendu',
            'email' => 'suspendu@example.com',
            'password' => 'ProPassword123!',
            'account_type' => 'pro',
            'status' => 'suspended',
            'professional_status' => 'suspended',
        ]);

        $response = $this->from($this->proHost . '/compte/connexion')->post($this->proHost . '/compte/connexion', [
            'identifier' => 'suspendu@example.com',
            'password' => 'ProPassword123!',
        ]);

        $response->assertRedirect($this->proHost . '/compte/connexion');
        $response->assertSessionHasErrors('identifier');
        $this->assertGuest('pro');
    }
}

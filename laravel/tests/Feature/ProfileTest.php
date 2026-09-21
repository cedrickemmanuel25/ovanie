<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                '_token' => 'test-token',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
                '_token' => 'test-token',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    /**
     * OVANIE n'effectue pas de suppression physique des comptes :
     * les données personnelles sont anonymisées pour garantir la traçabilité
     * des commandes, paiements et historiques de livraison.
     */
    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->delete('/profile', [
                'password' => 'password',
                '_token' => 'test-token',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        $anonymized = $user->fresh();
        $this->assertNotNull($anonymized);
        $this->assertSame('Compte supprimé', $anonymized->name);
        $this->assertSame('suspended', $anonymized->status);
        $this->assertNull($anonymized->phone);
        $this->assertStringStartsWith('deleted+', $anonymized->email);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
                '_token' => 'test-token',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}

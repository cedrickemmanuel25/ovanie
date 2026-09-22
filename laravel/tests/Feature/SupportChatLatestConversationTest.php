<?php

namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Demande utilisateur : à l'ouverture du panneau IA, proposer un choix
 * explicite entre "reprendre notre dernière conversation" et "nouvelle
 * conversation" plutôt que de toujours repartir de zéro. Cet endpoint
 * donne au panneau de quoi décider s'il doit proposer ce choix.
 */
class SupportChatLatestConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_with_no_prior_conversation_gets_exists_false(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $response = $this->getJson('/api/support/chat/latest');

        $response->assertOk();
        $response->assertJson(['exists' => false]);
    }

    public function test_a_client_with_an_active_conversation_gets_its_token_and_last_message(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $conversation = SupportConversation::create([
            'requester_user_id' => $client->id,
            'channel' => 'chat',
            'status' => 'active',
        ]);
        SupportConversationMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_type' => 'ai',
            'body' => 'Votre commande est en cours de livraison.',
        ]);

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/support/chat/latest');

        $response->assertOk();
        $response->assertJsonPath('exists', true);
        $response->assertJsonPath('conversation_token', $conversation->public_token);
        $response->assertJsonPath('preview', 'Votre commande est en cours de livraison.');
    }

    public function test_a_resolved_conversation_is_not_offered_for_resumption(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        SupportConversation::create([
            'requester_user_id' => $client->id,
            'channel' => 'chat',
            'status' => 'resolved',
        ]);

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/support/chat/latest');

        $response->assertOk();
        $response->assertJson(['exists' => false]);
    }

    public function test_a_client_never_sees_another_clients_conversation(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        SupportConversation::create([
            'requester_user_id' => $owner->id,
            'channel' => 'chat',
            'status' => 'active',
        ]);

        Sanctum::actingAs($other);
        $response = $this->getJson('/api/support/chat/latest');

        $response->assertOk();
        $response->assertJson(['exists' => false]);
    }

    public function test_a_guest_cannot_reach_the_endpoint(): void
    {
        $response = $this->getJson('/api/support/chat/latest');

        $response->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Devis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevisAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_devis(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownDevis = $this->devis($owner, 'Mon devis privé');
        $this->devis($other, 'Devis privé tiers');
        $this->actingAs($owner);

        $response = $this->getJson(route('api.devis.index'))->assertOk();

        $response->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $ownDevis->id);
        $this->assertStringNotContainsString('Devis privé tiers', $response->getContent());
    }

    public function test_user_cannot_view_update_or_delete_another_users_devis(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $devis = $this->devis($owner, 'Devis protégé');
        $this->actingAs($attacker);

        $this->getJson(route('api.devis.show', $devis))->assertForbidden();
        $this->putJson(route('api.devis.update', $devis), ['projet' => 'Tentative interdite'])->assertForbidden();
        $this->deleteJson(route('api.devis.destroy', $devis))->assertForbidden();

        $this->assertDatabaseHas('devis', ['id' => $devis->id, 'deleted_at' => null]);
    }

    public function test_creation_automatically_records_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson(route('api.devis.store'), $this->validPayload())
            ->assertCreated();

        $this->assertDatabaseHas('devis', ['user_id' => $user->id, 'projet' => 'Projet API sécurisé']);
    }

    public function test_user_id_from_request_is_ignored(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $payload = $this->validPayload();
        $payload['user_id'] = $other->id;

        $this->postJson(route('api.devis.store'), $payload)->assertCreated();

        $this->assertSame($user->id, Devis::query()->sole()->user_id);
    }

    public function test_unauthenticated_user_cannot_access_private_routes(): void
    {
        $this->getJson(route('api.devis.index'))->assertUnauthorized();
        $this->postJson(route('api.devis.store'), $this->validPayload())->assertUnauthorized();
    }

    public function test_authorized_internal_role_keeps_global_access(): void
    {
        $client = User::factory()->create();
        $devis = $this->devis($client, 'Devis visible commercial');
        $commercial = User::factory()->create(['role' => 'commercial', 'status' => 'active']);
        $this->actingAs($commercial);

        $this->getJson(route('api.devis.index'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->getJson(route('api.devis.show', $devis))
            ->assertOk()
            ->assertJsonPath('id', $devis->id);
    }

    private function devis(User $owner, string $project): Devis
    {
        return Devis::query()->create([
            'user_id' => $owner->id,
            'secteur' => 'BTP',
            'activites' => ['construction'],
            'prenom' => 'Client',
            'nom' => 'Autorisé',
            'email' => uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 1000000,
            'projet' => $project,
            'message' => 'Description suffisamment longue',
        ]);
    }

    private function validPayload(): array
    {
        return [
            'secteur' => 'BTP',
            'category' => 'Construction',
            'activites' => ['gros œuvre'],
            'prenom' => 'Client',
            'nom' => 'API',
            'email' => 'client-api-' . uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 1500000,
            'projet' => 'Projet API sécurisé',
            'message' => 'Description de projet suffisamment longue',
        ];
    }
}

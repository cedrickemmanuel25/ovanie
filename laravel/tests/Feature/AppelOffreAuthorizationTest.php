<?php

namespace Tests\Feature;

use App\Models\AppelOffre;
use App\Models\CommercialLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppelOffreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_calls_for_tender(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $own = $this->appel($owner, 'Mon appel privé');
        $this->appel($other, 'Appel privé tiers');

        $response = $this->actingAs($owner)->getJson(route('api.appels.index'))->assertOk();

        $response->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $own->id);
        $this->assertStringNotContainsString('Appel privé tiers', $response->getContent());
    }

    public function test_user_cannot_view_update_or_delete_another_users_tender(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $appel = $this->appel($owner, 'Appel protégé');

        $this->actingAs($attacker)->getJson(route('api.appels.show', $appel))->assertForbidden();
        $this->actingAs($attacker)->putJson(route('api.appels.update', $appel), [
            'description' => 'Modification interdite suffisamment longue',
        ])->assertForbidden();
        $this->actingAs($attacker)->deleteJson(route('api.appels.destroy', $appel))->assertForbidden();

        $this->assertDatabaseHas('appel_offres', ['id' => $appel->id, 'deleted_at' => null]);
    }

    public function test_creation_automatically_records_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.appels.store'), $this->validPayload())
            ->assertCreated();

        $this->assertDatabaseHas('appel_offres', ['user_id' => $user->id]);
    }

    public function test_attempt_to_change_user_or_company_is_ignored(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $payload = $this->validPayload() + ['user_id' => $other->id, 'company_id' => 999];

        $this->actingAs($user)->postJson(route('api.appels.store'), $payload)->assertCreated();

        $appel = AppelOffre::query()->sole();
        $this->assertSame($user->id, $appel->user_id);
        $this->assertNull($appel->company_id);

        $this->actingAs($user)->putJson(route('api.appels.update', $appel), [
            'description' => 'Description mise à jour et autorisée',
            'user_id' => $other->id,
            'company_id' => 999,
        ])->assertOk();

        $this->assertSame($user->id, $appel->fresh()->user_id);
        $this->assertNull($appel->fresh()->company_id);
    }

    public function test_unauthenticated_user_cannot_access_private_routes(): void
    {
        $this->getJson(route('api.appels.index'))->assertUnauthorized();
        $this->postJson(route('api.appels.store'), $this->validPayload())->assertUnauthorized();
    }

    public function test_authorized_internal_role_keeps_global_access(): void
    {
        $client = User::factory()->create();
        $appel = $this->appel($client, 'Appel visible commercial');
        $commercial = User::factory()->create(['role' => 'commercial', 'status' => 'active']);

        $this->actingAs($commercial)->getJson(route('api.appels.index'))
            ->assertOk()
            ->assertJsonPath('total', 1);
        $this->actingAs($commercial)->getJson(route('api.appels.show', $appel))
            ->assertOk()
            ->assertJsonPath('data.id', $appel->id);
    }

    public function test_tender_with_commercial_proposal_is_never_physically_deleted(): void
    {
        $owner = User::factory()->create();
        $appel = $this->appel($owner, 'Appel avec proposition');
        CommercialLead::query()->create([
            'user_id' => $owner->id,
            'appel_offre_id' => $appel->id,
            'contact_name' => 'Client Business',
            'title' => 'Proposition commerciale',
        ]);

        $this->actingAs($owner)->deleteJson(route('api.appels.destroy', $appel))->assertNoContent();

        $this->assertSoftDeleted('appel_offres', ['id' => $appel->id]);
        $this->assertNotNull(AppelOffre::withTrashed()->find($appel->id));
        $this->assertDatabaseHas('commercial_leads', ['appel_offre_id' => $appel->id]);
    }

    private function appel(User $owner, string $description): AppelOffre
    {
        return AppelOffre::query()->create([
            'user_id' => $owner->id,
            'secteur' => 'BTP',
            'services' => ['construction'],
            'prenom' => 'Client',
            'nom' => 'Business',
            'email' => uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 2000000,
            'delai' => 30,
            'description' => $description . ' suffisamment détaillé',
        ]);
    }

    private function validPayload(): array
    {
        return [
            'secteur' => 'BTP',
            'category' => 'Construction',
            'services' => ['gros œuvre'],
            'prenom' => 'Client',
            'nom' => 'API',
            'email' => 'appel-' . uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 2500000,
            'delai' => 45,
            'description' => 'Description appel offres suffisamment longue',
        ];
    }
}

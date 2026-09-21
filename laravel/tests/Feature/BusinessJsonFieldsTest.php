<?php

namespace Tests\Feature;

use App\Models\AppelOffre;
use App\Models\Devis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessJsonFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_activities_and_services_are_stored_as_valid_json_arrays(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('api.devis.store'), $this->devisPayload())->assertCreated();
        $this->actingAs($user)->postJson(route('api.appels.store'), $this->appelPayload())->assertCreated();

        $this->assertSame(['maçonnerie', 'plomberie'], json_decode(DB::table('devis')->value('activites'), true));
        $this->assertSame(['construction', 'livraison'], json_decode(DB::table('appel_offres')->value('services'), true));
    }

    public function test_json_fields_are_read_back_as_php_arrays(): void
    {
        $user = User::factory()->create();
        $devis = $this->devis($user, ['maçonnerie']);
        $appel = $this->appel($user, ['construction']);

        $this->assertIsArray($devis->fresh()->activites);
        $this->assertIsArray($appel->fresh()->services);
        $this->assertSame(['maçonnerie'], $devis->fresh()->activites);
        $this->assertSame(['construction'], $appel->fresh()->services);
    }

    public function test_update_does_not_double_encode_json(): void
    {
        $user = User::factory()->create();
        $devis = $this->devis($user, ['ancienne']);
        $appel = $this->appel($user, ['ancien']);

        $this->actingAs($user)->putJson(route('api.devis.update', $devis), [
            'activites' => ['nouvelle', 'finition'],
        ])->assertOk();
        $this->actingAs($user)->putJson(route('api.appels.update', $appel), [
            'services' => ['nouveau', 'transport'],
        ])->assertOk();

        $this->assertSame(['nouvelle', 'finition'], json_decode(DB::table('devis')->where('id', $devis->id)->value('activites'), true));
        $this->assertSame(['nouveau', 'transport'], json_decode(DB::table('appel_offres')->where('id', $appel->id)->value('services'), true));
    }

    public function test_legacy_double_encoded_value_is_repaired(): void
    {
        $user = User::factory()->create();
        $devis = $this->devis($user, ['temporaire']);
        $appel = $this->appel($user, ['temporaire']);
        DB::table('devis')->where('id', $devis->id)->update([
            'activites' => json_encode(json_encode(['réparée'], JSON_UNESCAPED_UNICODE)),
        ]);
        DB::table('appel_offres')->where('id', $appel->id)->update([
            'services' => json_encode(json_encode(['réparé'], JSON_UNESCAPED_UNICODE)),
        ]);

        $this->runRepairMigration();

        $this->assertSame(['réparée'], $devis->fresh()->activites);
        $this->assertSame(['réparé'], $appel->fresh()->services);
    }

    public function test_already_correct_value_remains_unchanged(): void
    {
        $user = User::factory()->create();
        $devis = $this->devis($user, ['déjà correcte']);
        $before = DB::table('devis')->where('id', $devis->id)->value('activites');

        $this->runRepairMigration();
        $this->runRepairMigration();

        $this->assertSame($before, DB::table('devis')->where('id', $devis->id)->value('activites'));
    }

    public function test_empty_fields_do_not_cause_server_error(): void
    {
        $user = User::factory()->create();
        $devis = $this->devis($user, []);
        $appel = $this->appel($user, []);

        $this->actingAs($user)->getJson(route('api.devis.show', $devis))
            ->assertOk()
            ->assertJsonPath('activites', []);
        $this->actingAs($user)->getJson(route('api.appels.show', $appel))
            ->assertOk()
            ->assertJsonPath('data.services', []);
    }

    private function runRepairMigration(): void
    {
        $migration = require database_path('migrations/2026_08_01_000006_repair_business_json_fields.php');
        $migration->up();
    }

    private function devis(User $user, array $activities): Devis
    {
        $attributes = $this->devisPayload();
        unset($attributes['category']);
        $attributes['user_id'] = $user->id;
        $attributes['activites'] = $activities;

        return Devis::query()->create($attributes);
    }

    private function appel(User $user, array $services): AppelOffre
    {
        $attributes = $this->appelPayload();
        unset($attributes['category']);
        $attributes['user_id'] = $user->id;
        $attributes['services'] = $services;

        return AppelOffre::query()->create($attributes);
    }

    private function devisPayload(): array
    {
        return [
            'secteur' => 'BTP', 'category' => 'Construction',
            'activites' => ['maçonnerie', 'plomberie'],
            'prenom' => 'Client', 'nom' => 'JSON',
            'email' => uniqid() . '@example.test', 'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan', 'budget' => 1000000, 'projet' => 'Projet JSON',
            'message' => 'Description suffisamment longue',
        ];
    }

    private function appelPayload(): array
    {
        return [
            'secteur' => 'BTP', 'category' => 'Construction',
            'services' => ['construction', 'livraison'],
            'prenom' => 'Client', 'nom' => 'JSON',
            'email' => uniqid() . '@example.test', 'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan', 'budget' => 2000000, 'delai' => 30,
            'description' => 'Description suffisamment longue',
        ];
    }
}

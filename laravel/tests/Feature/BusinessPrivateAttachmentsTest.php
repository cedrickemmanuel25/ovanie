<?php

namespace Tests\Feature;

use App\Models\AppelOffre;
use App\Models\Devis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessPrivateAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_new_business_attachments_are_stored_on_private_disk(): void
    {
        $user = User::factory()->create();
        $devisResponse = $this->actingAs($user)->post(route('api.devis.store'), $this->devisPayload([
            'image' => UploadedFile::fake()->image('plan-secret.jpg'),
        ]))->assertCreated();
        $appelResponse = $this->actingAs($user)->post(route('api.appels.store'), $this->appelPayload([
            'image' => UploadedFile::fake()->image('chantier-secret.png'),
        ]))->assertCreated();

        $devis = Devis::query()->sole();
        $appel = AppelOffre::query()->sole();
        Storage::disk('local')->assertExists($devis->image_path);
        Storage::disk('local')->assertExists($appel->image);
        Storage::disk('public')->assertMissing($devis->image_path);
        Storage::disk('public')->assertMissing($appel->image);
        $this->assertStringNotContainsString('plan-secret', $devis->image_path);
        $this->assertStringNotContainsString('chantier-secret', $appel->image);
        $this->assertStringNotContainsString('/storage/', $devisResponse->getContent());
        $this->assertStringNotContainsString('/storage/', $appelResponse->getContent());
    }

    public function test_no_direct_public_url_is_returned(): void
    {
        [$owner, $devis] = $this->storedDevis();

        $response = $this->actingAs($owner)->getJson(route('api.devis.show', $devis))->assertOk();

        $response->assertJsonMissingPath('image_url');
        $this->assertStringNotContainsString('/storage/', $response->getContent());
    }

    public function test_owner_can_download_private_attachment(): void
    {
        [$owner, $devis] = $this->storedDevis();

        $response = $this->actingAs($owner)
            ->get(route('api.devis.attachment', $devis))
            ->assertOk()
            ->assertHeader('Content-Disposition');

        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_other_user_receives_forbidden(): void
    {
        [, $devis] = $this->storedDevis();

        $this->actingAs(User::factory()->create())
            ->get(route('api.devis.attachment', $devis))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_download_attachment(): void
    {
        [, $devis] = $this->storedDevis();

        $this->getJson(route('api.devis.attachment', $devis))->assertUnauthorized();
    }

    public function test_missing_file_returns_not_found_without_server_error(): void
    {
        $owner = User::factory()->create();
        $devis = $this->devisModel($owner, 'private-documents/business/devis/absent.jpg');

        $this->actingAs($owner)
            ->get(route('api.devis.attachment', $devis))
            ->assertNotFound();
    }

    public function test_replacement_deletes_old_private_file(): void
    {
        [$owner, $devis] = $this->storedDevis();
        $oldPath = $devis->image_path;

        $this->actingAs($owner)->put(route('api.devis.update', $devis), [
            'image' => UploadedFile::fake()->image('nouveau-plan.webp'),
        ])->assertOk();

        $newPath = $devis->fresh()->image_path;
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->assertNotSame($oldPath, $newPath);
    }

    private function storedDevis(): array
    {
        $owner = User::factory()->create();
        $path = UploadedFile::fake()->image('document-confidentiel.jpg')
            ->store('private-documents/business/devis', 'local');

        return [$owner, $this->devisModel($owner, $path)];
    }

    private function devisModel(User $owner, string $path): Devis
    {
        $attributes = $this->devisPayload([
            'user_id' => $owner->id,
            'image_path' => $path,
        ]);
        unset($attributes['category']);

        return Devis::query()->create($attributes);
    }

    private function devisPayload(array $overrides = []): array
    {
        return array_merge([
            'secteur' => 'BTP',
            'category' => 'Construction',
            'activites' => ['plans'],
            'prenom' => 'Client',
            'nom' => 'Privé',
            'email' => uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 1000000,
            'projet' => 'Projet confidentiel',
            'message' => 'Description confidentielle assez longue',
        ], $overrides);
    }

    private function appelPayload(array $overrides = []): array
    {
        return array_merge([
            'secteur' => 'BTP',
            'category' => 'Construction',
            'services' => ['plans'],
            'prenom' => 'Client',
            'nom' => 'Privé',
            'email' => uniqid() . '@example.test',
            'telephone' => '0700000000',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'budget' => 2000000,
            'delai' => 30,
            'description' => 'Description confidentielle assez longue',
        ], $overrides);
    }
}

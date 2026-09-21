<?php

namespace Tests\Feature;

use App\Models\Devis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessPublicDataSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_api_never_returns_private_contact_data_or_internal_ids(): void
    {
        $this->createSensitiveDevis();

        $response = $this->getJson(route('business.json'))->assertOk();
        $json = $response->getContent();

        foreach (['Awa', 'Kouassi', 'awa.privee@example.test', '0708091011', 'Rue 12 Cocody', 'piece-identite.pdf'] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $json);
        }

        $item = $response->json('data.0');
        foreach (['nom', 'prenom', 'email', 'telephone', 'address', 'user_id', 'company_id', 'vendor_id', 'documents', 'image_path'] as $privateKey) {
            $this->assertArrayNotHasKey($privateKey, $item);
        }
    }

    public function test_required_public_information_remains_available(): void
    {
        $this->createSensitiveDevis();

        $item = $this->getJson(route('business.json'))->assertOk()->json('data.0');

        $this->assertSame([
            'public_id', 'request_type', 'title', 'category', 'approximate_area',
            'budget_range', 'description', 'deadline', 'published_at', 'public_status',
        ], array_keys($item));
        $this->assertSame('devis', $item['request_type']);
        $this->assertSame('Construction', $item['title']);
        $this->assertSame('Abidjan', $item['approximate_area']);
    }

    public function test_public_list_is_paginated_and_capped(): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->createSensitiveDevis('projet-' . $index);
        }

        $response = $this->getJson(route('business.json', ['per_page' => 2]))->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->getJson(route('business.json', ['per_page' => 500]))
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_unknown_request_does_not_cause_server_error(): void
    {
        $this->getJson('/api/business/json/biz_inconnue')->assertNotFound();
        $this->getJson(route('business.json', ['page' => 999]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function createSensitiveDevis(?string $suffix = null): Devis
    {
        return Devis::query()->create([
            'secteur' => 'BTP',
            'activites' => [],
            'prenom' => 'Awa',
            'nom' => 'Kouassi',
            'email' => ($suffix ?: 'awa.privee') . '@example.test',
            'telephone' => '0708091011',
            'pays' => 'Côte d’Ivoire',
            'ville' => 'Abidjan',
            'image_path' => 'prive/piece-identite.pdf',
            'budget' => 1500000,
            'projet' => 'Construction',
            'message' => '<b>Maison familiale</b> — email awa.privee@example.test — téléphone 0708091011. Adresse: Rue 12 Cocody',
        ]);
    }
}

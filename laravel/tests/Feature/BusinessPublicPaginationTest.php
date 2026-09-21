<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BusinessPublicPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_is_paginated_with_twenty_items_by_default(): void
    {
        $this->createDevis(55);

        $response = $this->getJson(route('business.json'))->assertOk();

        $response->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 55)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_per_page_cannot_exceed_fifty(): void
    {
        $this->createDevis(55);

        $this->getJson(route('business.json', ['per_page' => 500]))
            ->assertOk()
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_request_without_limit_never_returns_all_requests(): void
    {
        $this->createDevis(55);

        $response = $this->getJson(route('business.json'))->assertOk();

        $this->assertCount(20, $response->json('data'));
        $this->assertGreaterThan(count($response->json('data')), $response->json('meta.total'));
    }

    public function test_pages_have_no_duplicates(): void
    {
        $this->createDevis(45);

        $first = collect($this->getJson(route('business.json', ['page' => 1]))->json('data'))->pluck('public_id');
        $second = collect($this->getJson(route('business.json', ['page' => 2]))->json('data'))->pluck('public_id');
        $third = collect($this->getJson(route('business.json', ['page' => 3]))->json('data'))->pluck('public_id');

        $this->assertCount(45, $first->merge($second)->merge($third)->unique());
    }

    public function test_sort_is_stable_by_date_then_id_descending(): void
    {
        $this->createDevis(25);

        $firstPage = collect($this->getJson(route('business.json'))->json('data'))->pluck('title')->all();
        $secondPage = collect($this->getJson(route('business.json', ['page' => 2]))->json('data'))->pluck('title')->all();

        $this->assertSame(array_map(fn ($id) => 'Projet ' . $id, range(25, 6)), $firstPage);
        $this->assertSame(array_map(fn ($id) => 'Projet ' . $id, range(5, 1)), $secondPage);
    }

    public function test_nonexistent_page_is_safe_and_parameters_must_be_positive_integers(): void
    {
        $this->createDevis(2);

        $this->getJson(route('business.json', ['page' => 999]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson(route('business.json', ['page' => 0]))->assertUnprocessable();
        $this->getJson(route('business.json', ['per_page' => 'abc']))->assertUnprocessable();
    }

    public function test_public_route_has_a_dedicated_throttle(): void
    {
        $route = Route::getRoutes()->getByName('business.json');

        $this->assertNotNull($route);
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    private function createDevis(int $count): void
    {
        $now = now()->startOfSecond();
        $rows = [];

        foreach (range(1, $count) as $id) {
            $rows[] = [
                'secteur' => 'BTP',
                'activites' => '[]',
                'prenom' => 'Privé',
                'nom' => 'Masqué',
                'email' => 'prive-' . $id . '@example.test',
                'telephone' => '0700000000',
                'pays' => 'Côte d’Ivoire',
                'ville' => 'Abidjan',
                'budget' => 1000000,
                'projet' => 'Projet ' . $id,
                'message' => 'Description publique ' . $id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('devis')->insert($rows);
    }
}

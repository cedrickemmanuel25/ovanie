<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivitySector;
use App\Models\BusinessRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_create_update_or_delete_activity(): void
    {
        [$sector, $activity] = $this->activity();
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->postJson('/api/activities', $this->payload($sector))->assertForbidden();
        $this->actingAs($client)->patchJson("/api/activities/{$activity->id}", ['name' => 'Altérée'])->assertForbidden();
        $this->actingAs($client)->deleteJson("/api/activities/{$activity->id}")->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'name' => 'Maçonnerie']);
    }

    public function test_vendor_cannot_create_update_or_delete_activity(): void
    {
        [$sector, $activity] = $this->activity();
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($vendor)->postJson('/api/activities', $this->payload($sector))->assertForbidden();
        $this->actingAs($vendor)->putJson("/api/activities/{$activity->id}", ['name' => 'Altérée'])->assertForbidden();
        $this->actingAs($vendor)->deleteJson("/api/activities/{$activity->id}")->assertForbidden();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'name' => 'Maçonnerie']);
    }

    public function test_authorized_administrator_can_manage_activities(): void
    {
        [$sector, $activity] = $this->activity();
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $created = $this->actingAs($admin)->postJson('/api/activities', $this->payload($sector))
            ->assertCreated();
        $createdId = $created->json('id');
        $this->actingAs($admin)->patchJson("/api/activities/{$activity->id}", ['name' => 'Maçonnerie générale'])
            ->assertOk()->assertJsonPath('name', 'Maçonnerie générale');
        $this->actingAs($admin)->deleteJson("/api/activities/{$createdId}")->assertOk();
        $this->assertDatabaseMissing('activities', ['id' => $createdId]);
    }

    public function test_get_routes_remain_publicly_accessible(): void
    {
        [, $activity] = $this->activity();

        $this->getJson('/api/activities')->assertOk()->assertJsonFragment(['id' => $activity->id]);
        $this->getJson("/api/activities/{$activity->id}")->assertOk()->assertJsonPath('id', $activity->id);
    }

    public function test_used_activity_is_deactivated_instead_of_physically_deleted(): void
    {
        [, $activity] = $this->activity();
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        BusinessRequest::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Projet utilisant une activité',
            'description' => 'Demande réelle',
            'sector' => 'BTP',
            'category' => $activity->slug,
            'type' => 'quote',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->deleteJson("/api/activities/{$activity->id}")
            ->assertOk()->assertJsonPath('activity.is_active', false);
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'is_active' => false]);
    }

    public function test_unauthenticated_writes_are_rejected_without_modification(): void
    {
        [$sector, $activity] = $this->activity();

        $this->postJson('/api/activities', $this->payload($sector))->assertUnauthorized();
        $this->patchJson("/api/activities/{$activity->id}", ['name' => 'Altérée'])->assertUnauthorized();
        $this->deleteJson("/api/activities/{$activity->id}")->assertUnauthorized();
        $this->assertDatabaseHas('activities', ['id' => $activity->id, 'name' => 'Maçonnerie']);
    }

    private function activity(): array
    {
        $sector = ActivitySector::query()->create([
            'name' => 'Construction', 'slug' => 'construction-' . uniqid(), 'is_active' => true,
        ]);
        $activity = Activity::query()->create([
            'activity_sector_id' => $sector->id,
            'name' => 'Maçonnerie', 'slug' => 'maconnerie-' . uniqid(), 'is_active' => true,
        ]);

        return [$sector, $activity];
    }

    private function payload(ActivitySector $sector): array
    {
        return [
            'activity_sector_id' => $sector->id,
            'name' => 'Nouvelle activité',
            'slug' => 'nouvelle-activite-' . uniqid(),
            'is_active' => true,
        ];
    }
}

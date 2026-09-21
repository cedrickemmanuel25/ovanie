<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use App\Models\VendorPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateDocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_is_read_only_and_repeatable(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('legacy/identity.pdf', 'sensitive');
        $vendor = User::factory()->create(['role' => 'vendor']);
        $shop = Shop::create($this->shopAttributes($vendor, 'Boutique test', 'boutique-test') + ['identity_file' => 'legacy/identity.pdf']);

        $this->artisan('documents:migrate-private', ['--dry-run' => true])->assertSuccessful();
        $this->artisan('documents:migrate-private', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('legacy/identity.pdf', $shop->fresh()->identity_file);
        Storage::disk('public')->assertExists('legacy/identity.pdf');
        Storage::disk('local')->assertMissing('private-documents');
    }

    public function test_vendor_cannot_read_another_vendors_receipt_and_owner_can(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private-documents/payout-receipt/test.pdf', 'receipt');
        $owner = User::factory()->create(['role' => 'vendor']);
        $other = User::factory()->create(['role' => 'vendor']);
        Shop::create($this->shopAttributes($owner, 'Owner', 'owner'));
        Shop::create($this->shopAttributes($other, 'Other', 'other'));
        $payout = VendorPayout::create(['vendor_id' => $owner->id, 'transfer_receipt_path' => 'private-documents/payout-receipt/test.pdf']);

        $this->actingAs($other)->get(route('vendor.private-documents.payout', $payout))->assertForbidden();
        $this->actingAs($owner)->get(route('vendor.private-documents.payout', $payout))->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_private_document_routes_do_not_generate_public_storage_urls(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_contains((string) $route->getName(), 'private-documents'));
        $this->assertNotEmpty($routes);
        $this->assertTrue($routes->every(fn ($route) => ! str_contains($route->uri(), 'storage')));
    }

    private function shopAttributes(User $user, string $name, string $slug): array
    {
        return [
            'user_id' => $user->id, 'name' => $name, 'slug' => $slug, 'seller_type' => 'particulier',
            'city' => 'Abidjan', 'identity_type' => 'cni', 'identity_number' => 'TEST-'.$user->id,
            'mm_operator' => 'orange', 'mm_number' => '00000000', 'mm_holder' => $name,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ProPublication;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Compte simple OVANIE PRO : publier une offre ou un besoin. */
class ProPublicationTest extends TestCase
{
    use RefreshDatabase;

    private string $host;
    private Category $root;
    private Category $sub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = 'https://' . config('ovanie.pro_domain');
        Storage::fake('public');
        Storage::fake('local');
        $this->root = Category::query()->create(['name' => 'Matériaux de construction', 'slug' => 'materiaux', 'status' => 'actif', 'is_active' => true]);
        $this->sub = Category::query()->create(['name' => 'Ciment', 'slug' => 'ciment', 'parent_id' => $this->root->id, 'status' => 'actif', 'is_active' => true]);
    }

    public function test_guest_chooses_offer_creates_account_then_publishes(): void
    {
        $this->get($this->host . '/compte/publier')->assertOk()->assertSee('Que souhaitez-vous publier ?');
        $this->post($this->host . '/compte/publier', [])->assertSessionHasErrors('type');
        $this->post($this->host . '/compte/publier', ['type' => 'offre'])->assertRedirect(route('pro.publish.account', ['type' => 'offre']));

        $this->get($this->host . '/compte/publier/offre/compte')->assertOk()->assertSee('Créez votre compte');
        $this->post($this->host . '/compte/publier/offre/compte', [
            'last_name' => 'Diarra', 'first_name' => 'Moussa', 'residence_country' => 'Côte d’Ivoire', 'phone_country' => '+225',
            'phone' => '0712345678', 'email' => 'moussa@example.com', 'preferred_language' => 'fr',
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => '1',
        ])->assertRedirect(route('pro.publish.form', ['type' => 'offre']));

        $user = ProUser::query()->where('email', 'moussa@example.com')->firstOrFail();
        $this->assertSame('simple', $user->account_type);
        $this->assertTrue($user->hasRole('advertiser'));

        $form = $this->offerData();
        $this->post($this->host . '/compte/publier/offre/nouveau', ['action' => 'continue'] + array_diff_key($form, ['photos' => 1]))
            ->assertSessionHasErrors('photos');

        $response = $this->post($this->host . '/compte/publier/offre/nouveau', ['action' => 'continue'] + $form);
        $offer = ProPublication::query()->firstOrFail();
        $response->assertRedirect(route('pro.publish.review', ['type' => 'offre', 'publication' => $offer]));
        $this->assertSame('draft', $offer->status);
        $this->assertSame(2, $offer->photos()->count());
        Storage::disk('public')->assertExists($offer->photos()->first()->path);

        $review = $this->get($this->host . "/compte/publier/offre/{$offer->id}/verification");
        $review->assertOk()->assertSee('Vérifiez votre offre')->assertSee('Lot de 500 sacs de ciment')->assertSee('500 sacs');
        $review->assertDontSee('moussa@example.com');

        $this->post($this->host . "/compte/publier/offre/{$offer->id}/publier", [])->assertSessionHasErrors(['confirm_accuracy', 'confirm_rights', 'accept_rules']);
        $this->post($this->host . "/compte/publier/offre/{$offer->id}/publier", ['confirm_accuracy' => '1', 'confirm_rights' => '1', 'accept_rules' => '1'])
            ->assertRedirect(route('pro.account'));
        $this->assertSame('published', $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->published_at);
    }

    public function test_need_can_be_saved_as_draft_and_validates_conditional_fields(): void
    {
        $user = $this->user();
        $url = $this->host . '/compte/publier/besoin/nouveau';

        $this->actingAs($user, 'pro')->post($url, ['action' => 'draft', 'title' => 'Recherche blocs'])->assertSessionHasNoErrors();
        $need = ProPublication::query()->firstOrFail();
        $this->assertSame('need', $need->kind);
        $this->assertTrue($user->fresh()->hasRole('requester'));

        $base = [
            'action' => 'continue', 'nature' => 'product', 'title' => 'Recherche 1 000 blocs', 'category_id' => $this->root->id,
            'subcategory_id' => $this->sub->id, 'description' => 'Blocs pour chantier.', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan',
            'deadline' => 'date', 'budget_mode' => 'range',
        ];
        $this->actingAs($user, 'pro')->post($this->host . "/compte/publier/besoin/{$need->id}/modifier", $base)
            ->assertSessionHasErrors(['quantity', 'unit', 'needed_by', 'budget_min', 'budget_max']);

        $this->actingAs($user, 'pro')->post($this->host . "/compte/publier/besoin/{$need->id}/modifier", array_merge($base, [
            'quantity_to_define' => '1', 'deadline' => '15', 'budget_mode' => 'proposals',
        ]))->assertRedirect(route('pro.publish.review', ['type' => 'besoin', 'publication' => $need]));
        $this->assertTrue($need->fresh()->quantity_to_define);
    }

    public function test_subcategory_must_belong_to_category_and_publication_is_private_to_its_owner(): void
    {
        $user = $this->user();
        $other = Category::query()->create(['name' => 'Électricité', 'slug' => 'elec', 'status' => 'actif', 'is_active' => true]);
        $data = ['action' => 'continue', 'category_id' => $other->id] + $this->offerData();
        $this->actingAs($user, 'pro')->post($this->host . '/compte/publier/offre/nouveau', $data)->assertSessionHasErrors('subcategory_id');

        $this->actingAs($user, 'pro')->post($this->host . '/compte/publier/offre/nouveau', ['action' => 'draft', 'title' => 'Mon offre']);
        $offer = ProPublication::query()->firstOrFail();
        $intruder = $this->user('intrus@example.com', '+2250700000002');
        $this->actingAs($intruder, 'pro')->get($this->host . "/compte/publier/offre/{$offer->id}/verification")->assertNotFound();
        $this->actingAs($user, 'pro')->get($this->host . "/compte/publier/besoin/{$offer->id}/verification")->assertNotFound();
    }

    public function test_unfinished_simple_account_returns_to_its_publication_instead_of_account_space(): void
    {
        $user = $this->user();
        $user->roles()->create(['role' => 'requester']);
        $account = $this->host . '/compte/mon-espace';

        $this->actingAs($user, 'pro')->get($account)->assertRedirect(route('pro.publish.form', ['type' => 'besoin']));

        $this->actingAs($user, 'pro')->post($this->host . '/compte/publier/besoin/nouveau', ['action' => 'draft', 'title' => 'Blocs']);
        $draft = ProPublication::query()->firstOrFail();
        $this->actingAs($user, 'pro')->get($account)->assertRedirect(route('pro.publish.edit', ['type' => 'besoin', 'publication' => $draft]));

        $draft->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->actingAs($user, 'pro')->get($account)->assertOk();
    }

    public function test_old_simple_registration_link_leads_to_the_new_journey(): void
    {
        $this->get($this->host . '/compte/inscription/simple?purpose=requester')->assertRedirect(route('pro.publish.start', ['type' => 'besoin']));
    }

    private function offerData(): array
    {
        return [
            'nature' => 'product', 'title' => 'Lot de 500 sacs de ciment', 'category_id' => $this->root->id, 'subcategory_id' => $this->sub->id,
            'description' => 'Ciment disponible immédiatement.', 'quantity' => '500', 'unit' => 'sac_50', 'price' => '5500', 'currency' => 'XOF',
            'price_negotiable' => '1', 'availability' => 'immediate', 'country' => 'Côte d’Ivoire', 'city' => 'Abidjan',
            'validity_days' => '30', 'expires_on' => now()->addDays(30)->toDateString(),
            'photos' => [UploadedFile::fake()->image('ciment.jpg', 800, 600), UploadedFile::fake()->image('palette.png', 800, 600)],
        ];
    }

    private function user(string $email = 'demandeur@example.com', string $phone = '+2250700000001'): ProUser
    {
        return ProUser::query()->create([
            'first_name' => 'Awa', 'last_name' => 'Koné', 'name' => 'Awa Koné', 'email' => $email, 'phone' => $phone,
            'residence_country' => 'Côte d’Ivoire', 'password' => 'Secret123!', 'account_type' => 'simple', 'onboarding_path' => 'simple',
            'status' => 'active', 'professional_status' => 'not_started',
        ]);
    }
}

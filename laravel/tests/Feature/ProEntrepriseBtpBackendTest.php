<?php

namespace Tests\Feature;

use App\Models\ProDocument;
use App\Models\ProProfessionalProfile;
use App\Models\ProUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProEntrepriseBtpBackendTest extends TestCase
{
    use RefreshDatabase;

    private string $proHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->proHost = 'https://' . config('ovanie.pro_domain');
        Storage::fake('local');
    }

    public function test_entreprise_btp_uses_dedicated_six_section_screen(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'pro')
            ->get($this->proHost . '/compte/professionnel/entreprise_btp')
            ->assertOk()
            ->assertSee('Entreprise BTP')
            ->assertSee('Activité BTP')
            ->assertSee('Organisation')
            ->assertSee('btp_functions', false)
            ->assertSee('documents[company_statutes]', false);
    }

    public function test_empty_final_submission_is_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user, 'pro')
            ->from($this->proHost . '/compte/professionnel/entreprise_btp')
            ->post($this->proHost . '/compte/professionnel/entreprise_btp', ['action' => 'submit'])
            ->assertSessionHasErrors([
                'legal_name','legal_form','registration_country','address','registration_number','tax_number',
                'responsible_position','professional_phone','professional_email','specialties','work_types','intervention_zones','project_categories',
                'purchase_material_categories','purchase_equipment_categories','purchase_local_countries','organisation_completed','btp_functions',
                'documents.registry','documents.tax_certificate','documents.company_statutes','documents.representative_id',
            ]);
    }

    public function test_submit_persists_company_business_data_and_documents(): void
    {
        $user = $this->user();
        $payload = [
            'action'=>'submit','legal_name'=>'BTP Africa Construction SARL','trade_name'=>'BTP Africa','legal_form'=>'sarl',
            'registration_country'=>'Côte d’Ivoire','address'=>'Plateau, Abidjan','registration_number'=>'CI-ABJ-2026-B-7788','tax_number'=>'1234567 F',
            'responsible_position'=>'directeur_travaux','professional_phone'=>'+2250701020304','professional_whatsapp'=>'+2250701020304','professional_email'=>'contact@btp-africa.test','website'=>'https://btp-africa.test',
            'specialties'=>['residentiel','routes','genie_civil'],'work_types'=>['construction_neuve','genie_civil'],'intervention_zones'=>'Côte d’Ivoire, Ghana, Sénégal','project_categories'=>'Logements individuels, Routes & ponts','years_experience'=>'10_20',
            'purchase_material_categories'=>'Ciment, Aciers et fers à béton','purchase_equipment_categories'=>'Engins de chantier, Outillage','purchase_local_countries'=>'Côte d’Ivoire, Ghana','purchase_international_countries'=>'Chine, Turquie','purchase_frequency'=>'mensuelle','purchase_volume'=>'important',
            'organisation_completed'=>'yes','purchasing_manager_first_name'=>'Fatoumata','purchasing_manager_last_name'=>'Diarra','purchasing_manager_email'=>'achats@btp-africa.test','purchasing_manager_phone'=>'+2250701020305',
            'btp_functions'=>['rfq','orders','supplier_search','subcontracting'],
            'documents'=>[
                'registry'=>UploadedFile::fake()->create('rccm.pdf',100,'application/pdf'),
                'tax_certificate'=>UploadedFile::fake()->create('fiscal.pdf',100,'application/pdf'),
                'company_statutes'=>UploadedFile::fake()->create('statuts.pdf',100,'application/pdf'),
                'representative_id'=>UploadedFile::fake()->image('cni.jpg'),
            ],
        ];
        $this->actingAs($user,'pro')
            ->post($this->proHost . '/compte/professionnel/entreprise_btp',$payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pro.roles.edit'));
        $profile=$user->fresh()->professionalProfile()->with('organization')->firstOrFail();
        $this->assertSame('BTP Africa Construction SARL',$profile->organization->legal_name);
        $this->assertSame(['residentiel','routes','genie_civil'],$profile->profile_data['specialties']);
        $this->assertSame(['rfq','orders','supplier_search','subcontracting'],$profile->profile_data['btp_functions']);
        foreach(['registry','tax_certificate','company_statutes','representative_id'] as $type){
            $this->assertTrue(ProDocument::query()->where('pro_organization_id',$profile->organization->id)->where('document_type',$type)->exists());
        }
    }

    private function user(): ProUser
    {
        $user=ProUser::query()->create([
            'first_name'=>'Yao','last_name'=>'Kouakou','name'=>'Yao Kouakou','email'=>'btp@example.com','phone'=>'+2250701020304',
            'residence_country'=>'Côte d’Ivoire','preferred_language'=>'fr','password'=>'ProPassword123','account_type'=>'pro','onboarding_path'=>'professional',
            'status'=>'active','professional_status'=>'draft','locale'=>'fr','currency'=>'XOF',
        ]);
        ProProfessionalProfile::query()->create([
            'pro_user_id'=>$user->id,'profile_key'=>'entreprise_btp','entity_type'=>'organization','activity_country'=>'Côte d’Ivoire',
            'registration_country'=>'Côte d’Ivoire','desired_functions'=>['buy','publish'],'status'=>'draft',
        ]);
        return $user->fresh();
    }
}

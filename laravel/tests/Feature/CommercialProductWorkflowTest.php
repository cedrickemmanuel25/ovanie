<?php

namespace Tests\Feature;

use App\Http\Controllers\Commercial\CommercialProductController;
use App\Models\Shop;
use App\Services\ProductImageNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class CommercialProductWorkflowTest extends TestCase
{
    private function rules(bool $publishing): array
    {
        $method = new ReflectionMethod(CommercialProductController::class, 'rules');
        $rules = $method->invoke(new CommercialProductController(), collect([12]), $publishing);
        $rules['category_id'] = array_values(array_filter($rules['category_id'], fn ($rule) => $rule !== 'exists:categories,id'));
        return $rules;
    }

    private function publishPayload(): array
    {
        return ['intent'=>'publish','shop_id'=>12,'category_id'=>1,'name'=>'Ciment','description'=>'Produit complet','price'=>5000,'unit'=>'sac','min_order_quantity'=>1,'weight_kg'=>50,'length_cm'=>60,'width_cm'=>40,'height_cm'=>20,'fragile'=>'0','requires_unloading'=>'0'];
    }

    public function test_publication_is_refused_without_weight(): void
    {
        $data=$this->publishPayload(); unset($data['weight_kg']);
        $this->assertTrue(Validator::make($data,$this->rules(true))->errors()->has('weight_kg'));
    }

    public function test_publication_is_refused_without_dimensions(): void
    {
        $data=$this->publishPayload(); unset($data['length_cm'],$data['width_cm'],$data['height_cm']);
        $errors=Validator::make($data,$this->rules(true))->errors();
        $this->assertTrue($errors->has('length_cm') && $errors->has('width_cm') && $errors->has('height_cm'));
    }

    public function test_draft_accepts_incomplete_product_information(): void
    {
        $data=['intent'=>'draft','shop_id'=>12];
        $this->assertFalse(Validator::make($data,$this->rules(false))->fails());
    }

    public function test_server_recalculates_volume_and_shop_controls_activation(): void
    {
        $shop=new Shop(['status'=>'approved','is_active'=>true,'logistics_status'=>'ready','logistics_type'=>'ovanie','address'=>'Rue 1','commune'=>'Cocody','district'=>'Anono','latitude'=>5.3,'longitude'=>-4.0,'geo_status'=>'verified']);
        $method=new ReflectionMethod(CommercialProductController::class,'productAttributes');
        $attributes=$method->invoke(new CommercialProductController(),$this->publishPayload(),true,$shop);
        $this->assertSame(0.048,$attributes['volume_m3']);
        $this->assertSame('actif',$attributes['status']);
        $this->assertTrue($attributes['is_active']);
        $shop->logistics_status='incomplete';
        $attributes=$method->invoke(new CommercialProductController(),$this->publishPayload(),true,$shop);
        $this->assertSame('pending_logistics',$attributes['status']);
        $this->assertFalse($attributes['is_active']);
    }

    public function test_normalizer_generates_1200_800_and_400_webp_variants(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) $this->markTestSkipped('GD WEBP indisponible.');
        Storage::fake('public');
        $result=app(ProductImageNormalizer::class)->normalize(UploadedFile::fake()->image('produit.jpg',1000,1000),'products');
        foreach (['master'=>1200,'card'=>800,'thumb'=>400] as $key=>$size) {
            Storage::disk('public')->assertExists($result[$key]);
            [$width,$height]=getimagesize(Storage::disk('public')->path($result[$key]));
            $this->assertSame([$size,$size],[$width,$height]);
        }
    }

    public function test_controller_requires_an_image_to_publish_and_preserves_existing_images_on_update(): void
    {
        $source=file_get_contents(app_path('Http/Controllers/Commercial/CommercialProductController.php'));
        $this->assertStringContainsString("if (\$publishing && (\$existingCount + count(\$newFiles)) < 1)",$source);
        $this->assertStringContainsString("whereIn('id', \$removeIds)->delete()",$source);
        $this->assertStringNotContainsString("images()->delete();",$source);
        $this->assertStringContainsString("\$mainId = (int) \$images->first()?->id",$source);
    }

    public function test_editing_an_unmanaged_product_is_forbidden_by_ownership_check(): void
    {
        $source=file_get_contents(app_path('Http/Controllers/Commercial/CommercialProductController.php'));
        $this->assertStringContainsString('abort_unless($this->managedShops($request)->whereKey($product->shop_id)->exists(), 403);',$source);
    }
}

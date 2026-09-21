<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasTable('commercial_capture_sessions')) Schema::create('commercial_capture_sessions', function(Blueprint $t){
   $t->id(); $t->foreignId('commercial_id')->constrained('users')->cascadeOnDelete(); $t->foreignId('shop_id')->constrained()->cascadeOnDelete();
   $t->string('name'); $t->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete(); $t->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
   $t->string('status',30)->default('draft')->index(); $t->boolean('keep_category')->default(true); $t->text('notes')->nullable(); $t->timestamp('finished_at')->nullable(); $t->timestamps();
  });
  if (!Schema::hasTable('commercial_capture_session_products')) Schema::create('commercial_capture_session_products', function(Blueprint $t){
   $t->id(); $t->foreignId('session_id')->constrained('commercial_capture_sessions')->cascadeOnDelete(); $t->foreignId('product_id')->constrained()->cascadeOnDelete(); $t->string('capture_status',30)->default('draft'); $t->timestamps(); $t->unique(['session_id','product_id']);
  });
 }
 public function down(): void { Schema::dropIfExists('commercial_capture_session_products'); Schema::dropIfExists('commercial_capture_sessions'); }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('shops', function(Blueprint $t){ $t->json('mobile_presentation')->nullable(); });
  Schema::table('reviews', function(Blueprint $t){ $t->text('vendor_reply')->nullable(); $t->timestamp('vendor_replied_at')->nullable(); });
 }
 public function down(): void {
  Schema::table('shops', fn(Blueprint $t)=>$t->dropColumn('mobile_presentation'));
  Schema::table('reviews', fn(Blueprint $t)=>$t->dropColumn(['vendor_reply','vendor_replied_at']));
 }
};

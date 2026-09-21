<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('short_description')->nullable()->after('description');
            $table->text('technical_details')->nullable()->after('short_description');
            $table->decimal('promo_price', 10, 2)->nullable()->after('price');
            $table->boolean('commission_paid')->default(false)->after('promo_price');
            $table->boolean('lock_contacts')->default(false)->after('commission_paid');
            $table->string('main_image')->nullable()->after('images');
            $table->json('gallery')->nullable()->after('main_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'short_description',
                'technical_details',
                'promo_price',
                'commission_paid',
                'lock_contacts',
                'main_image',
                'gallery'
            ]);
        });
    }
};

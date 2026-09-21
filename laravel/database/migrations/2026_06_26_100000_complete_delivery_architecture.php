<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Mode logistique de la boutique
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                if (! Schema::hasColumn('shops', 'logistics_type')) {
                    $table->string('logistics_type', 30)->default('seller');
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Services de livraison
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('delivery_services')) {
            Schema::create('delivery_services', function (Blueprint $table) {
                $table->id();

                if (Schema::hasTable('carriers')) {
                    $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('carrier_id')->nullable()->index();
                }

                $table->string('provider_type', 30)->default('seller'); // seller, ovanie, partner
                $table->string('code', 80)->unique();
                $table->string('name');
                $table->text('description')->nullable();

                $table->unsignedSmallInteger('estimated_hours')->default(48);
                $table->unsignedSmallInteger('min_estimated_hours')->nullable();
                $table->unsignedSmallInteger('max_estimated_hours')->nullable();

                $table->decimal('max_weight_kg', 12, 3)->nullable();
                $table->decimal('max_volume_m3', 12, 4)->nullable();
                $table->decimal('max_length_cm', 12, 2)->nullable();
                $table->decimal('max_width_cm', 12, 2)->nullable();
                $table->decimal('max_height_cm', 12, 2)->nullable();

                $table->json('available_days')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();

                $table->unsignedInteger('sort_order')->default(100);
                $table->boolean('is_active')->default(true);
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index(['provider_type', 'is_active'], 'ds_provider_active_idx');
                $table->index(['carrier_id', 'is_active'], 'ds_carrier_active_idx');
                $table->index('sort_order', 'ds_sort_order_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Zones couvertes par les services de livraison
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('delivery_service_zones')) {
            Schema::create('delivery_service_zones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_service_id')->constrained('delivery_services')->cascadeOnDelete();

                $table->string('country', 100)->default("Côte d'Ivoire");
                $table->string('region', 100)->nullable();
                $table->string('delivery_zone', 30)->nullable(); // abidjan, interieur, national
                $table->string('city', 150)->nullable();
                $table->string('commune', 150)->nullable();
                $table->string('district', 150)->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['delivery_service_id', 'is_active'], 'dsz_service_active_idx');
                $table->index(['delivery_zone', 'city', 'commune'], 'dsz_zone_city_commune_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Tarifs des services de livraison
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('delivery_service_rates')) {
            Schema::create('delivery_service_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('delivery_service_id')->constrained('delivery_services')->cascadeOnDelete();
                $table->foreignId('delivery_service_zone_id')->nullable()->constrained('delivery_service_zones')->nullOnDelete();

                $table->string('delivery_zone', 30)->nullable();
                $table->string('city', 150)->nullable();
                $table->string('commune', 150)->nullable();

                $table->decimal('base_fee', 12, 2)->default(0);
                $table->decimal('price_per_kg', 12, 2)->default(0);
                $table->decimal('price_per_m3', 12, 2)->default(0);
                $table->decimal('price_per_km', 12, 2)->default(0);
                $table->decimal('fragile_fee', 12, 2)->default(0);
                $table->decimal('unloading_fee', 12, 2)->default(0);
                $table->decimal('urgent_fee', 12, 2)->default(0);

                $table->decimal('min_fee', 12, 2)->nullable();
                $table->decimal('max_fee', 12, 2)->nullable();

                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['delivery_service_id', 'is_active'], 'dsr_service_active_idx');
                $table->index(['delivery_zone', 'city', 'commune'], 'dsr_zone_city_commune_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Services de livraison disponibles par produit
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('product_delivery_service')) {
            Schema::create('product_delivery_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('delivery_service_id')->constrained('delivery_services')->cascadeOnDelete();

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['product_id', 'delivery_service_id'], 'pds_product_service_unique');
                $table->index(['delivery_service_id', 'is_active'], 'pds_service_active_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Option de livraison choisie au checkout
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('order_delivery_selections')) {
            Schema::create('order_delivery_selections', function (Blueprint $table) {
                $table->id();

                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

                if (Schema::hasTable('shops')) {
                    $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('shop_id')->nullable()->index();
                }

                $table->foreignId('delivery_service_id')->nullable()->constrained('delivery_services')->nullOnDelete();

                if (Schema::hasTable('carriers')) {
                    $table->foreignId('carrier_id')->nullable()->constrained('carriers')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('carrier_id')->nullable()->index();
                }

                $table->string('service_code', 100)->nullable();
                $table->string('service_name')->nullable();
                $table->string('provider_type', 30)->nullable();

                $table->unsignedSmallInteger('estimated_hours')->nullable();
                $table->decimal('delivery_fee', 12, 2)->default(0);

                $table->string('delivery_zone', 30)->nullable();
                $table->string('delivery_city', 150)->nullable();
                $table->string('delivery_commune', 150)->nullable();

                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'shop_id'], 'ods_order_shop_idx');
                $table->index(['delivery_service_id', 'provider_type'], 'ods_service_provider_idx');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Informations de livraison dans la commande
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'delivery_destination_type')) {
                    $table->string('delivery_destination_type', 30)->nullable(); // home, site
                }

                if (! Schema::hasColumn('orders', 'delivery_site_name')) {
                    $table->string('delivery_site_name')->nullable();
                }

                if (! Schema::hasColumn('orders', 'delivery_recipient_name')) {
                    $table->string('delivery_recipient_name')->nullable();
                }

                if (! Schema::hasColumn('orders', 'delivery_recipient_phone')) {
                    $table->string('delivery_recipient_phone', 30)->nullable();
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Informations de service dans les expéditions
        |--------------------------------------------------------------------------
        */

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (! Schema::hasColumn('shipments', 'shop_id')) {
                    if (Schema::hasTable('shops')) {
                        $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
                    } else {
                        $table->unsignedBigInteger('shop_id')->nullable()->index();
                    }
                }

                if (! Schema::hasColumn('shipments', 'delivery_service_id')) {
                    $table->foreignId('delivery_service_id')->nullable()->constrained('delivery_services')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                if (Schema::hasColumn('shipments', 'delivery_service_id')) {
                    $table->dropConstrainedForeignId('delivery_service_id');
                }

                if (Schema::hasColumn('shipments', 'shop_id')) {
                    $table->dropConstrainedForeignId('shop_id');
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = [
                    'delivery_destination_type',
                    'delivery_site_name',
                    'delivery_recipient_name',
                    'delivery_recipient_phone',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('order_delivery_selections');
        Schema::dropIfExists('product_delivery_service');
        Schema::dropIfExists('delivery_service_rates');
        Schema::dropIfExists('delivery_service_zones');
        Schema::dropIfExists('delivery_services');

        if (Schema::hasTable('shops')) {
            Schema::table('shops', function (Blueprint $table) {
                if (Schema::hasColumn('shops', 'logistics_type')) {
                    $table->dropColumn('logistics_type');
                }
            });
        }
    }
};
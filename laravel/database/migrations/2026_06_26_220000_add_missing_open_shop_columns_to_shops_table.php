<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'seller_type')) {
                $table->string('seller_type', 30)->default('particulier');
            }

            if (! Schema::hasColumn('shops', 'description')) {
                $table->text('description')->nullable();
            }

            if (! Schema::hasColumn('shops', 'region')) {
                $table->string('region', 100)->nullable();
            }

            if (! Schema::hasColumn('shops', 'city')) {
                $table->string('city', 150)->nullable();
            }

            if (! Schema::hasColumn('shops', 'commune')) {
                $table->string('commune', 150)->nullable();
            }

            if (! Schema::hasColumn('shops', 'district')) {
                $table->string('district', 150)->nullable();
            }

            if (! Schema::hasColumn('shops', 'landmark')) {
                $table->string('landmark')->nullable();
            }

            if (! Schema::hasColumn('shops', 'address')) {
                $table->string('address')->nullable();
            }

            if (! Schema::hasColumn('shops', 'main_category')) {
                $table->string('main_category', 150)->nullable();
            }

            if (! Schema::hasColumn('shops', 'delivery_zone')) {
                $table->string('delivery_zone', 60)->nullable();
            }

            if (! Schema::hasColumn('shops', 'logistics_type')) {
                $table->string('logistics_type', 30)->default('seller');
            }

            if (! Schema::hasColumn('shops', 'processing_time')) {
                $table->string('processing_time', 60)->nullable();
            }

            if (! Schema::hasColumn('shops', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable();
            }

            if (! Schema::hasColumn('shops', 'business_email')) {
                $table->string('business_email')->nullable();
            }

            if (! Schema::hasColumn('shops', 'identity_country')) {
                $table->string('identity_country', 10)->default('ci');
            }

            if (! Schema::hasColumn('shops', 'identity_type')) {
                $table->string('identity_type', 50)->nullable();
            }

            if (! Schema::hasColumn('shops', 'identity_number')) {
                $table->string('identity_number', 100)->nullable()->index();
            }

            if (! Schema::hasColumn('shops', 'identity_upload_mode')) {
                $table->string('identity_upload_mode', 30)->default('pdf');
            }

            if (! Schema::hasColumn('shops', 'direct_payment')) {
                $table->boolean('direct_payment')->default(false);
            }

            if (! Schema::hasColumn('shops', 'payment_mode')) {
                $table->string('payment_mode', 50)->default('post_delivery');
            }

            if (! Schema::hasColumn('shops', 'mm_operator')) {
                $table->string('mm_operator', 50)->nullable();
            }

            if (! Schema::hasColumn('shops', 'mm_number')) {
                $table->string('mm_number', 30)->nullable();
            }

            if (! Schema::hasColumn('shops', 'mm_holder')) {
                $table->string('mm_holder')->nullable();
            }

            if (! Schema::hasColumn('shops', 'status')) {
                $table->string('status', 30)->default('approved')->index();
            }

            if (! Schema::hasColumn('shops', 'is_active')) {
                $table->boolean('is_active')->default(true)->index();
            }

            if (! Schema::hasColumn('shops', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable();
            }

            if (! Schema::hasColumn('shops', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }

            if (! Schema::hasColumn('shops', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->index();
            }

            if (! Schema::hasColumn('shops', 'selfie')) {
                $table->string('selfie')->nullable();
            }

            if (! Schema::hasColumn('shops', 'identity_file')) {
                $table->string('identity_file')->nullable();
            }

            if (! Schema::hasColumn('shops', 'identity_file_back')) {
                $table->string('identity_file_back')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        $columns = [
            'seller_type',
            'description',
            'region',
            'city',
            'commune',
            'district',
            'landmark',
            'address',
            'main_category',
            'delivery_zone',
            'logistics_type',
            'processing_time',
            'whatsapp',
            'business_email',
            'identity_country',
            'identity_type',
            'identity_number',
            'identity_upload_mode',
            'direct_payment',
            'payment_mode',
            'mm_operator',
            'mm_number',
            'mm_holder',
            'status',
            'is_active',
            'rejection_reason',
            'approved_at',
            'reviewed_by',
            'selfie',
            'identity_file',
            'identity_file_back',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('shops', $column)) {
                Schema::table('shops', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

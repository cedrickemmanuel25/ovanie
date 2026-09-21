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
            if (! Schema::hasColumn('shops', 'region')) {
                $table->string('region', 100)->nullable()->after('city');
            }

            if (! Schema::hasColumn('shops', 'commune')) {
                $table->string('commune', 100)->nullable()->after('region');
            }

            if (! Schema::hasColumn('shops', 'district')) {
                $table->string('district', 100)->nullable()->after('commune');
            }

            if (! Schema::hasColumn('shops', 'landmark')) {
                $table->string('landmark')->nullable()->after('district');
            }

            if (! Schema::hasColumn('shops', 'main_category')) {
                $table->string('main_category', 100)->nullable()->after('description');
            }

            if (! Schema::hasColumn('shops', 'delivery_zone')) {
                $table->string('delivery_zone', 100)->nullable()->after('main_category');
            }

            if (! Schema::hasColumn('shops', 'processing_time')) {
                $table->string('processing_time', 50)->nullable()->after('delivery_zone');
            }

            if (! Schema::hasColumn('shops', 'whatsapp')) {
                $table->string('whatsapp', 30)->nullable()->after('address');
            }

            if (! Schema::hasColumn('shops', 'business_email')) {
                $table->string('business_email')->nullable()->after('whatsapp');
            }

            if (! Schema::hasColumn('shops', 'company_name')) {
                $table->string('company_name')->nullable()->after('seller_type');
            }

            if (! Schema::hasColumn('shops', 'legal_form')) {
                $table->string('legal_form', 50)->nullable()->after('company_name');
            }

            if (! Schema::hasColumn('shops', 'rccm')) {
                $table->string('rccm', 100)->nullable()->after('legal_form');
            }

            if (! Schema::hasColumn('shops', 'taxpayer_number')) {
                $table->string('taxpayer_number', 100)->nullable()->after('rccm');
            }

            if (! Schema::hasColumn('shops', 'identity_country')) {
                $table->string('identity_country', 10)->nullable()->after('identity_type');
            }

            if (! Schema::hasColumn('shops', 'identity_upload_mode')) {
                $table->string('identity_upload_mode', 20)->nullable()->after('identity_number');
            }

            if (! Schema::hasColumn('shops', 'identity_file_front')) {
                $table->string('identity_file_front')->nullable()->after('identity_file');
            }

            if (! Schema::hasColumn('shops', 'identity_file_back')) {
                $table->string('identity_file_back')->nullable()->after('identity_file_front');
            }

            if (! Schema::hasColumn('shops', 'selfie')) {
                $table->string('selfie')->nullable()->after('logo');
            }

            if (! Schema::hasColumn('shops', 'rccm_file')) {
                $table->string('rccm_file')->nullable()->after('identity_file_back');
            }

            if (! Schema::hasColumn('shops', 'tax_file')) {
                $table->string('tax_file')->nullable()->after('rccm_file');
            }

            if (! Schema::hasColumn('shops', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('shops', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('shops', 'reviewed_by')) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shops')) {
            return;
        }

        Schema::table('shops', function (Blueprint $table) {
            if (Schema::hasColumn('shops', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            $columns = [
                'region',
                'commune',
                'district',
                'landmark',
                'main_category',
                'delivery_zone',
                'processing_time',
                'whatsapp',
                'business_email',
                'company_name',
                'legal_form',
                'rccm',
                'taxpayer_number',
                'identity_country',
                'identity_upload_mode',
                'identity_file_front',
                'identity_file_back',
                'selfie',
                'rccm_file',
                'tax_file',
                'rejection_reason',
                'approved_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('shops', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
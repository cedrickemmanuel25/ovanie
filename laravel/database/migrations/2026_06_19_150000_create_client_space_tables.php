<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('favorites')) {
            Schema::create('favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'product_id']);
            });
        }

        if (!Schema::hasTable('client_payment_methods')) {
            Schema::create('client_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('type')->default('mobile_money');
                $table->string('operator');
                $table->string('account_name')->nullable();
                $table->string('phone');
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('addresses', 'type')) {
                $table->string('type')->default('home')->after('user_id');
            }
            if (!Schema::hasColumn('addresses', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('label');
            }
            if (!Schema::hasColumn('addresses', 'commune')) {
                $table->string('commune')->nullable()->after('city');
            }
            if (!Schema::hasColumn('addresses', 'country')) {
                $table->string('country')->default("Côte d'Ivoire")->after('commune');
            }
            if (!Schema::hasColumn('addresses', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('addresses', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            if (!Schema::hasColumn('users', 'secondary_phone')) {
                $table->string('secondary_phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }
            if (!Schema::hasColumn('users', 'gender')) {
                $table->string('gender')->nullable();
            }
            if (!Schema::hasColumn('users', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('users', 'account_type')) {
                $table->string('account_type')->default('particulier');
            }
            if (!Schema::hasColumn('users', 'loyalty_points')) {
                $table->unsignedInteger('loyalty_points')->default(0);
            }
            if (!Schema::hasColumn('users', 'preferred_cities')) {
                $table->json('preferred_cities')->nullable();
            }
            if (!Schema::hasColumn('users', 'favorite_categories')) {
                $table->json('favorite_categories')->nullable();
            }
            if (!Schema::hasColumn('users', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable();
            }
            if (!Schema::hasColumn('users', 'locale')) {
                $table->string('locale')->default('fr');
            }
            if (!Schema::hasColumn('users', 'currency')) {
                $table->string('currency')->default('XOF');
            }
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default('Africa/Abidjan');
            }
            if (!Schema::hasColumn('users', 'date_format')) {
                $table->string('date_format')->default('d/m/Y');
            }
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code')->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            foreach (['avatar', 'secondary_phone', 'birth_date', 'gender', 'city', 'account_type', 'loyalty_points', 'preferred_cities', 'favorite_categories', 'notification_preferences', 'locale', 'currency', 'timezone', 'date_format', 'referral_code'] as $col) {
                if (Schema::hasColumn('users', $col)) $cols[] = $col;
            }
            if (!empty($cols)) $table->dropColumn($cols);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $cols = [];
            foreach (['type', 'recipient_name', 'commune', 'country', 'latitude', 'longitude'] as $col) {
                if (Schema::hasColumn('addresses', $col)) $cols[] = $col;
            }
            if (!empty($cols)) $table->dropColumn($cols);
        });

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('client_payment_methods');
        Schema::dropIfExists('favorites');
    }
};

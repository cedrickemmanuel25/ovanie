<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('client_payment_methods')) {
            return;
        }

        Schema::table('client_payment_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('client_payment_methods', 'card_brand')) {
                $table->string('card_brand', 30)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('client_payment_methods', 'card_last4')) {
                $table->string('card_last4', 4)->nullable()->after('card_brand');
            }
            if (! Schema::hasColumn('client_payment_methods', 'card_exp_month')) {
                $table->unsignedTinyInteger('card_exp_month')->nullable()->after('card_last4');
            }
            if (! Schema::hasColumn('client_payment_methods', 'card_exp_year')) {
                $table->unsignedSmallInteger('card_exp_year')->nullable()->after('card_exp_month');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('client_payment_methods')) {
            return;
        }

        $columns = [];
        foreach (['card_brand', 'card_last4', 'card_exp_month', 'card_exp_year'] as $column) {
            if (Schema::hasColumn('client_payment_methods', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('client_payment_methods', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

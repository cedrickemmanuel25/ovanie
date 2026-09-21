<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'loyalty_debt')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedInteger('loyalty_debt')->default(0);
            });
        }

        if (Schema::hasTable('returns')) {
            Schema::table('returns', function (Blueprint $table) {
                if (! Schema::hasColumn('returns', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable();
                }

                if (! Schema::hasColumn('returns', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable();
                }

                if (! Schema::hasColumn('returns', 'refund_prepared_at')) {
                    $table->timestamp('refund_prepared_at')->nullable();
                }

                if (! Schema::hasColumn('returns', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'loyalty_debt')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('loyalty_debt');
            });
        }

        if (Schema::hasTable('returns')) {
            $columns = collect(['accepted_at', 'rejected_at', 'refund_prepared_at', 'refunded_at'])
                ->filter(fn (string $column) => Schema::hasColumn('returns', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                Schema::table('returns', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};

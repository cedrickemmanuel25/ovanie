<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'order_id' => fn (Blueprint $table) => $table->foreignId('order_id')->nullable()->after('id')->constrained()->nullOnDelete(),
            'order_item_id' => fn (Blueprint $table) => $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete(),
            'client_id' => fn (Blueprint $table) => $table->foreignId('client_id')->nullable()->after('order_item_id')->constrained('users')->nullOnDelete(),
            'shop_id' => fn (Blueprint $table) => $table->foreignId('shop_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete(),
            'status' => fn (Blueprint $table) => $table->string('status')->default('open')->after('reason'),
            'internal_notes' => fn (Blueprint $table) => $table->text('internal_notes')->nullable()->after('response'),
            'deleted_at' => fn (Blueprint $table) => $table->softDeletes(),
        ];
        foreach ($columns as $name => $definition) {
            if (! Schema::hasColumn('disputes', $name)) {
                Schema::table('disputes', $definition);
            }
        }

        Schema::table('disputes', function (Blueprint $table) {
            $table->index(['client_id', 'created_at']);
            $table->index(['vendor_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
        });

        DB::table('disputes')->orderBy('id')->each(function ($dispute) {
            $order = DB::table('orders')->where('order_number', $dispute->order_reference)->first();
            if (! $order) return;
            $shopId = $order->shop_id ?? null;
            $vendorId = $order->vendor_id ?? null;
            if ($shopId) {
                $vendorId = DB::table('shops')->where('id', $shopId)->value('user_id') ?? $vendorId;
            }
            DB::table('disputes')->where('id', $dispute->id)->update([
                'order_id' => $order->id,
                'client_id' => $order->client_id,
                'vendor_id' => $vendorId,
                'shop_id' => $shopId,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['order_id']); $table->dropForeign(['order_item_id']);
            $table->dropForeign(['client_id']); $table->dropForeign(['shop_id']);
            $table->dropIndex(['client_id', 'created_at']); $table->dropIndex(['vendor_id', 'created_at']); $table->dropIndex(['shop_id', 'created_at']);
            $table->dropColumn(['order_id', 'order_item_id', 'client_id', 'shop_id', 'status', 'internal_notes', 'deleted_at']);
        });
    }
};

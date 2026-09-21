<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCategoryAndTypeToBusinessRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('business_requests', function (Blueprint $table) {
            $table->string('category')->nullable()->after('sector');
            $table->string('type')->nullable()->after('category');
        });
    }

    public function down()
    {
        Schema::table('business_requests', function (Blueprint $table) {
            $table->dropColumn(['category', 'type']);
        });
    }
}

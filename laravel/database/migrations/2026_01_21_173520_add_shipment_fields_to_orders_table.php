<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShipmentFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Ajout de la colonne vendor_id (référence au vendeur)
            $table->foreignId('vendor_id')->after('client_id')->constrained('users')->cascadeOnDelete();

            // Transporteur
            $table->string('carrier')->nullable()->after('status');

            // Numéro de suivi colis
            $table->string('tracking_number')->nullable()->after('carrier');

            // Date d'expédition
            $table->date('shipment_date')->nullable()->after('tracking_number');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['vendor_id', 'carrier', 'tracking_number', 'shipment_date']);
        });
    }
}

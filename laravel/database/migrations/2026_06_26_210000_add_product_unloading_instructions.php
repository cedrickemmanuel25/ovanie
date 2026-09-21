<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Ajouter requires_unloading si la colonne n'existe pas encore
        |--------------------------------------------------------------------------
        | Cette colonne indique si le produit nécessite une aide au déchargement.
        | Exemple : ciment, sable, gravier, palette, carrelage lourd, fer à béton.
        */

        if (! Schema::hasColumn('products', 'requires_unloading')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'is_fragile')) {
                    $table->boolean('requires_unloading')
                        ->default(false)
                        ->after('is_fragile');
                } elseif (Schema::hasColumn('products', 'volume_m3')) {
                    $table->boolean('requires_unloading')
                        ->default(false)
                        ->after('volume_m3');
                } else {
                    $table->boolean('requires_unloading')
                        ->default(false);
                }
            });
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Ajouter unloading_instructions après requires_unloading
        |--------------------------------------------------------------------------
        | Cette colonne permet de préciser le type de déchargement :
        | manutentionnaires, chariot élévateur, grue, camion benne, etc.
        */

        if (! Schema::hasColumn('products', 'unloading_instructions')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'requires_unloading')) {
                    $table->string('unloading_instructions')
                        ->nullable()
                        ->after('requires_unloading');
                } else {
                    $table->string('unloading_instructions')
                        ->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unloading_instructions')) {
                $table->dropColumn('unloading_instructions');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'requires_unloading')) {
                $table->dropColumn('requires_unloading');
            }
        });
    }
};
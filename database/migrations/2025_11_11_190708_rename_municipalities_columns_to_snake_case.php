<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            // Renommer les colonnes selon les conventions Laravel (snake_case)
            $table->renameColumn('CODE_COM', 'code');
            $table->renameColumn('LIB_COMMUNE', 'name');
            $table->renameColumn('MODE_GEST_VOIE', 'road_management_mode');
            $table->renameColumn('MODE_GEST_PARC', 'park_management_mode');
            $table->renameColumn('LAST_NUM_VOIE', 'last_road_number');
            $table->renameColumn('CODE_POSTAL', 'postal_code');
            $table->renameColumn('LIB_COMMUNE_MIN', 'display_name');
            $table->renameColumn('FORMAT_PARC', 'park_format');
            $table->renameColumn('code_commune_with_division', 'code_with_division');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            // Restaurer les noms de colonnes d'origine
            $table->renameColumn('code', 'CODE_COM');
            $table->renameColumn('name', 'LIB_COMMUNE');
            $table->renameColumn('road_management_mode', 'MODE_GEST_VOIE');
            $table->renameColumn('park_management_mode', 'MODE_GEST_PARC');
            $table->renameColumn('last_road_number', 'LAST_NUM_VOIE');
            $table->renameColumn('postal_code', 'CODE_POSTAL');
            $table->renameColumn('display_name', 'LIB_COMMUNE_MIN');
            $table->renameColumn('park_format', 'FORMAT_PARC');
            $table->renameColumn('code_with_division', 'code_commune_with_division');
        });
    }
};

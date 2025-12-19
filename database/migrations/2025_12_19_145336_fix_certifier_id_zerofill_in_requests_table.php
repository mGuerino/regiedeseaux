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
        Schema::table('requests', function (Blueprint $table) {
            // Supprimer le ZEROFILL de certifier_id pour le rendre cohérent avec signatory_id et contact_person_id
            // Avant: bigint(20) unsigned zerofill
            // Après: bigint unsigned
            $table->unsignedBigInteger('certifier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            // Remettre le ZEROFILL si nécessaire (rollback)
            // Note: Laravel ne supporte pas directement ZEROFILL via Blueprint
            // Il faudrait utiliser DB::statement pour un rollback complet
            $table->unsignedBigInteger('certifier_id')->nullable()->change();
        });
    }
};

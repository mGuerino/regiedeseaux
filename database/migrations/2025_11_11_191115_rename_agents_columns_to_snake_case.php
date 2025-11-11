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
        Schema::table('agents', function (Blueprint $table) {
            // Renommer les colonnes selon les conventions Laravel (snake_case)
            $table->renameColumn('ID_AGENT', 'id');
            $table->renameColumn('TYPE_AGENT', 'type');
            $table->renameColumn('AGENT', 'name');
            $table->renameColumn('QUALITE', 'title');
            $table->renameColumn('QUALITE2', 'secondary_title');
            $table->renameColumn('TEL', 'phone');
            $table->renameColumn('EMAIL', 'email');
            $table->renameColumn('ACTIF', 'is_active');
            $table->renameColumn('FAX', 'fax');
            // IS_DEFAULT est déjà conventionnel (is_default)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            // Restaurer les noms de colonnes d'origine
            $table->renameColumn('id', 'ID_AGENT');
            $table->renameColumn('type', 'TYPE_AGENT');
            $table->renameColumn('name', 'AGENT');
            $table->renameColumn('title', 'QUALITE');
            $table->renameColumn('secondary_title', 'QUALITE2');
            $table->renameColumn('phone', 'TEL');
            $table->renameColumn('email', 'EMAIL');
            $table->renameColumn('is_active', 'ACTIF');
            $table->renameColumn('fax', 'FAX');
        });
    }
};

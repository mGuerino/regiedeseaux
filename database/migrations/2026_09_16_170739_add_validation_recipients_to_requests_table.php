<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Superviseurs prévenus lors de l'envoi en validation.
     *
     * L'agent choisit qui il alerte ; la validation reste ouverte à tous les
     * superviseurs. Conserver la liste permet de savoir, depuis la demande,
     * qui a été sollicité et relancer la bonne personne.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->json('validation_notified_to')->nullable()->after('validation_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('validation_notified_to');
        });
    }
};

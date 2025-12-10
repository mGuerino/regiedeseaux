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
            // Index pour les filtres par commune (très fréquent)
            $table->index('municipality_code', 'requests_municipality_code_index');
            
            // Index pour les filtres par statut
            $table->index('request_status', 'requests_request_status_index');
            
            // Index pour le tri par date (colonne de tri par défaut)
            $table->index('request_date', 'requests_request_date_index');
            
            // Index pour la jointure avec applicants
            $table->index('applicant_id', 'requests_applicant_id_index');
            
            // Index pour les requêtes soft-deleted
            $table->index('deleted_at', 'requests_deleted_at_index');
            
            // Index composite pour les widgets qui filtrent par commune + date
            $table->index(['municipality_code', 'request_date'], 'requests_municipality_date_index');
            
            // Index composite pour les widgets qui filtrent par statut + date
            $table->index(['request_status', 'request_date'], 'requests_status_date_index');
        });

        Schema::table('documents', function (Blueprint $table) {
            // Index pour la jointure avec requests
            $table->index('request_id', 'documents_request_id_index');
            
            // Index pour le nouveau champ document_type
            if (Schema::hasColumn('documents', 'document_type')) {
                $table->index('document_type', 'documents_document_type_index');
            }
        });

        Schema::table('request_road', function (Blueprint $table) {
            // Index pour la jointure avec requests
            $table->index('request_id', 'request_road_request_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex('requests_municipality_code_index');
            $table->dropIndex('requests_request_status_index');
            $table->dropIndex('requests_request_date_index');
            $table->dropIndex('requests_applicant_id_index');
            $table->dropIndex('requests_deleted_at_index');
            $table->dropIndex('requests_municipality_date_index');
            $table->dropIndex('requests_status_date_index');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_request_id_index');
            
            if (Schema::hasColumn('documents', 'document_type')) {
                $table->dropIndex('documents_document_type_index');
            }
        });

        Schema::table('request_road', function (Blueprint $table) {
            $table->dropIndex('request_road_request_id_index');
        });
    }
};

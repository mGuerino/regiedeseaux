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
        Schema::table('parcels', function (Blueprint $table) {
            // Index pour les recherches sur ident
            $table->index('ident', 'parcels_ident_index');
            
            // Index pour les filtres par commune
            $table->index('codcomm', 'parcels_codcomm_index');
            
            // Index composite pour les jointures optimisées
            $table->index(['codcomm', 'ident'], 'parcels_codcomm_ident_index');
        });

        Schema::table('parcel_request', function (Blueprint $table) {
            // Index pour les jointures avec requests
            $table->index('request_id', 'parcel_request_request_id_index');
            
            // Index pour les jointures avec parcels
            $table->index('parcel_id', 'parcel_request_parcel_id_index');
            
            // Index composite pour optimiser les requêtes complexes
            $table->index(['request_id', 'parcel_id'], 'parcel_request_composite_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropIndex('parcels_ident_index');
            $table->dropIndex('parcels_codcomm_index');
            $table->dropIndex('parcels_codcomm_ident_index');
        });

        Schema::table('parcel_request', function (Blueprint $table) {
            $table->dropIndex('parcel_request_request_id_index');
            $table->dropIndex('parcel_request_parcel_id_index');
            $table->dropIndex('parcel_request_composite_index');
        });
    }
};

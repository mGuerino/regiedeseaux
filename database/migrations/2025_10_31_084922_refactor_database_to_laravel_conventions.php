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
        // 1. Renommer les tables principales en anglais
        Schema::rename('ARRP_AGENTS', 'agents');
        Schema::rename('ARRP_COMMUNES', 'municipalities');
        Schema::rename('ARRP_DEMANDES', 'requests');
        Schema::rename('ARRP_DEMANDEURS', 'applicants');
        Schema::rename('ARRP_DOCUMENTS', 'documents');
        Schema::rename('ARRP_PARAMETRES', 'settings');
        Schema::rename('ARRP_VOIES', 'roads');
        
        // 2. Renommer les tables pivots
        Schema::rename('ARRP_DEMANDES_PARCELLES', 'parcel_request');
        Schema::rename('ARRP_DEMANDES_VOIES', 'request_road');
        
        // 3. Ajouter timestamps à parcels
        Schema::table('parcels', function (Blueprint $table) {
            $table->timestamps();
        });
        
        // 4. Renommer colonnes de la table requests
        Schema::table('requests', function (Blueprint $table) {
            $table->renameColumn('ID_DEMANDE', 'id');
            $table->renameColumn('ID_DEMANDEUR', 'applicant_id');
            $table->renameColumn('CONTACT', 'contact');
            $table->renameColumn('REFERENCE', 'reference');
            $table->renameColumn('DATE_DEMANDE', 'request_date');
            $table->renameColumn('DATE_REPONSE', 'response_date');
            $table->renameColumn('STATUT_DEMANDE', 'request_status');
            $table->renameColumn('STATUT_AEP', 'water_status');
            $table->renameColumn('STATUT_EU', 'wastewater_status');
            $table->renameColumn('OBSERVATIONS', 'observations');
            $table->renameColumn('ID_SIGNATAIRE', 'signatory_id');
            $table->renameColumn('URL_CARTE', 'map_url');
            $table->renameColumn('ID_ATTESTANT', 'certifier_id');
            $table->renameColumn('ID_INTERLOCUTEUR', 'contact_person_id');
            $table->renameColumn('USER_SAISIE', 'created_by');
            $table->renameColumn('DATE_SAISIE', 'created_date');
            $table->renameColumn('USER_MODIF', 'updated_by');
            $table->renameColumn('DATE_MODIF', 'updated_date');
            $table->renameColumn('CODE_COM', 'municipality_code');
        });
        
        // 5. Renommer colonnes de la table applicants
        Schema::table('applicants', function (Blueprint $table) {
            $table->renameColumn('NOM', 'last_name');
            $table->renameColumn('PRENOM', 'first_name');
            $table->renameColumn('ADRESSE', 'address');
            $table->renameColumn('ADRESSE2', 'address2');
            $table->renameColumn('CP', 'postal_code');
            $table->renameColumn('VILLE', 'city');
            $table->renameColumn('EMAIL', 'email');
            $table->renameColumn('TEL1', 'phone1');
            $table->renameColumn('TEL2', 'phone2');
            $table->renameColumn('OBSERVATIONS', 'observations');
            $table->renameColumn('USER_SAISIE', 'created_by');
            $table->renameColumn('DATE_SAISIE', 'created_date');
            $table->renameColumn('USER_MODIF', 'updated_by');
            $table->renameColumn('DATE_MODIF', 'updated_date');
        });
        
        // 6. Renommer colonnes de la table pivot parcel_request
        Schema::table('parcel_request', function (Blueprint $table) {
            $table->renameColumn('ID_DEM_PARC', 'id');
            $table->renameColumn('ID_DEMANDE', 'request_id');
            $table->renameColumn('ID_PARC', 'parcel_id');
            $table->renameColumn('LABX', 'label_x');
            $table->renameColumn('LABY', 'label_y');
            $table->renameColumn('NSEC', 'section_number');
        });
        
        // 7. Renommer colonnes de la table pivot request_road
        Schema::table('request_road', function (Blueprint $table) {
            $table->renameColumn('ID_DEM_VOIE', 'id');
            $table->renameColumn('ID_DEMANDE', 'request_id');
            $table->renameColumn('CDRURU', 'road_code');
            $table->renameColumn('LIBELLE_VOIE', 'road_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback dans l'ordre inverse
        
        Schema::table('request_road', function (Blueprint $table) {
            $table->renameColumn('id', 'ID_DEM_VOIE');
            $table->renameColumn('request_id', 'ID_DEMANDE');
            $table->renameColumn('road_code', 'CDRURU');
            $table->renameColumn('road_name', 'LIBELLE_VOIE');
        });
        
        Schema::table('parcel_request', function (Blueprint $table) {
            $table->renameColumn('id', 'ID_DEM_PARC');
            $table->renameColumn('request_id', 'ID_DEMANDE');
            $table->renameColumn('parcel_id', 'ID_PARC');
            $table->renameColumn('label_x', 'LABX');
            $table->renameColumn('label_y', 'LABY');
            $table->renameColumn('section_number', 'NSEC');
        });
        
        Schema::table('applicants', function (Blueprint $table) {
            $table->renameColumn('last_name', 'NOM');
            $table->renameColumn('first_name', 'PRENOM');
            $table->renameColumn('address', 'ADRESSE');
            $table->renameColumn('address2', 'ADRESSE2');
            $table->renameColumn('postal_code', 'CP');
            $table->renameColumn('city', 'VILLE');
            $table->renameColumn('email', 'EMAIL');
            $table->renameColumn('phone1', 'TEL1');
            $table->renameColumn('phone2', 'TEL2');
            $table->renameColumn('observations', 'OBSERVATIONS');
            $table->renameColumn('created_by', 'USER_SAISIE');
            $table->renameColumn('created_date', 'DATE_SAISIE');
            $table->renameColumn('updated_by', 'USER_MODIF');
            $table->renameColumn('updated_date', 'DATE_MODIF');
        });
        
        Schema::table('requests', function (Blueprint $table) {
            $table->renameColumn('id', 'ID_DEMANDE');
            $table->renameColumn('applicant_id', 'ID_DEMANDEUR');
            $table->renameColumn('contact', 'CONTACT');
            $table->renameColumn('reference', 'REFERENCE');
            $table->renameColumn('request_date', 'DATE_DEMANDE');
            $table->renameColumn('response_date', 'DATE_REPONSE');
            $table->renameColumn('request_status', 'STATUT_DEMANDE');
            $table->renameColumn('water_status', 'STATUT_AEP');
            $table->renameColumn('wastewater_status', 'STATUT_EU');
            $table->renameColumn('observations', 'OBSERVATIONS');
            $table->renameColumn('signatory_id', 'ID_SIGNATAIRE');
            $table->renameColumn('map_url', 'URL_CARTE');
            $table->renameColumn('certifier_id', 'ID_ATTESTANT');
            $table->renameColumn('contact_person_id', 'ID_INTERLOCUTEUR');
            $table->renameColumn('created_by', 'USER_SAISIE');
            $table->renameColumn('created_date', 'DATE_SAISIE');
            $table->renameColumn('updated_by', 'USER_MODIF');
            $table->renameColumn('updated_date', 'DATE_MODIF');
            $table->renameColumn('municipality_code', 'CODE_COM');
        });
        
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropTimestamps();
        });
        
        Schema::rename('request_road', 'ARRP_DEMANDES_VOIES');
        Schema::rename('parcel_request', 'ARRP_DEMANDES_PARCELLES');
        Schema::rename('roads', 'ARRP_VOIES');
        Schema::rename('settings', 'ARRP_PARAMETRES');
        Schema::rename('documents', 'ARRP_DOCUMENTS');
        Schema::rename('applicants', 'ARRP_DEMANDEURS');
        Schema::rename('requests', 'ARRP_DEMANDES');
        Schema::rename('municipalities', 'ARRP_COMMUNES');
        Schema::rename('agents', 'ARRP_AGENTS');
    }
};

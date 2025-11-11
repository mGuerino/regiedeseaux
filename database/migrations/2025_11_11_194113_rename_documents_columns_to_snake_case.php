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
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('ID_DOCUMENT', 'id');
            $table->renameColumn('ID_DEMANDE', 'request_id');
            $table->renameColumn('NOM_DOC', 'document_name');
            $table->renameColumn('NOM_FICHIER', 'file_name');
            $table->renameColumn('OBSERVATIONS', 'observations');
            $table->renameColumn('USER_SAISIE', 'created_by');
            $table->renameColumn('DATE_SAISIE', 'created_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->renameColumn('id', 'ID_DOCUMENT');
            $table->renameColumn('request_id', 'ID_DEMANDE');
            $table->renameColumn('document_name', 'NOM_DOC');
            $table->renameColumn('file_name', 'NOM_FICHIER');
            $table->renameColumn('observations', 'OBSERVATIONS');
            $table->renameColumn('created_by', 'USER_SAISIE');
            $table->renameColumn('created_date', 'DATE_SAISIE');
        });
    }
};

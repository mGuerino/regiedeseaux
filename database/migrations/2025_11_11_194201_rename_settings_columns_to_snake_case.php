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
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('PARAM_CODE', 'code');
            $table->renameColumn('PARAM_VALUE', 'value');
            $table->renameColumn('PARAM_DESC', 'description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->renameColumn('code', 'PARAM_CODE');
            $table->renameColumn('value', 'PARAM_VALUE');
            $table->renameColumn('description', 'PARAM_DESC');
        });
    }
};

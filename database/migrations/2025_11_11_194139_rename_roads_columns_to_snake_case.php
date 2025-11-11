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
        Schema::table('roads', function (Blueprint $table) {
            $table->renameColumn('CDRURU', 'code');
            $table->renameColumn('CODE_COM', 'municipality_code');
            $table->renameColumn('RUE', 'name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roads', function (Blueprint $table) {
            $table->renameColumn('code', 'CDRURU');
            $table->renameColumn('municipality_code', 'CODE_COM');
            $table->renameColumn('name', 'RUE');
        });
    }
};

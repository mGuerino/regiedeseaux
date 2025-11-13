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
        Schema::table('parcel_request', function (Blueprint $table) {
            $table->dropColumn(['section_number', 'parcel_name', 'label_x', 'label_y']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parcel_request', function (Blueprint $table) {
            $table->string('section_number')->nullable();
            $table->string('parcel_name')->nullable();
            $table->integer('label_x')->nullable();
            $table->integer('label_y')->nullable();
        });
    }
};

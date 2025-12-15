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
            $table->foreignId('followed_by_user_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('followed_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['followed_by_user_id']);
            $table->dropIndex(['followed_by_user_id']);
            $table->dropColumn('followed_by_user_id');
        });
    }
};

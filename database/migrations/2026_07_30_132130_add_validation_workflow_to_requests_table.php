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
            $table->string('validation_status', 20)->nullable()->after('request_status');
            $table->timestamp('validation_requested_at')->nullable()->after('validation_status');
            $table->foreignId('validation_requested_by')->nullable()->after('validation_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validation_requested_by');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('validated_by');

            $table->index('validation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['validation_requested_by']);
            $table->dropForeign(['validated_by']);
            $table->dropIndex(['validation_status']);
            $table->dropColumn([
                'validation_status',
                'validation_requested_at',
                'validation_requested_by',
                'validated_at',
                'validated_by',
                'rejection_reason',
            ]);
        });
    }
};

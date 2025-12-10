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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->text('message');
            $table->json('recipients');
            $table->json('document_ids');
            $table->string('sent_by');
            $table->unsignedInteger('recipients_count');
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('created_at');
            $table->index('sent_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

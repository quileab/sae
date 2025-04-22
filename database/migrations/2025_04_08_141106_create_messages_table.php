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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->nullable()->constrained('users')->onDelete('cascade'); // null para global o por materia
            $table->foreignId('subject_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('messageType', ['individual', 'subject', 'global'])->default('individual');
            $table->text('content');
            $table->string('attachment_path')->nullable(); // archivo adjunto
            $table->string('attachment_name')->nullable(); // nombre original del adjunto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
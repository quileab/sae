<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->timestamps();

            $table->index(['sender_id', 'subject_id', 'created_at'], 'messages_sender_subject_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

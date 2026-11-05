<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('configs_id', 30)->constrained('configs')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('type', 20)->default('csv-1');
            $table->string('value', 250)->nullable();
            $table->timestamps();

            $table->unique(['subject_id', 'user_id', 'configs_id'], 'unique_subject_user_config');
            $table->index(['configs_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};

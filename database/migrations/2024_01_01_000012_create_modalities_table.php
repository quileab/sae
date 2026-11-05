<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modalities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->string('description', 100)->nullable();
            $table->integer('percentage')->default(75);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modalities');
    }
};

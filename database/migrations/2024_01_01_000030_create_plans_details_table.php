<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plans_master_id')->constrained('plans_masters')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans_details');
    }
};

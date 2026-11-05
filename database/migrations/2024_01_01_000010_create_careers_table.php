<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('resolution', 40)->nullable();
            $table->boolean('allow_enrollments')->default(true);
            $table->boolean('allow_evaluations')->default(true);
            $table->unsignedInteger('enrollment_quota_2027')->nullable();
            $table->boolean('enrollment_quota_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('careers');
    }
};

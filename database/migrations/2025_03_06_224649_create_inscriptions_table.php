<?php

use App\Models\Subject;
use App\Models\User;
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
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Subject::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('configs_id', 30)->constrained('configs')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['csv-1', 'csv-n', 'text', 'bool', 'int'])->default('csv-1');
            $table->string('value', 250)->nullable();
            // $table->timestamps();
            $table->unique(['subject_id', 'user_id', 'configs_id'], 'unique_subject_user_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};

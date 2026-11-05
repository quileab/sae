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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('publisher')->nullable();
            $table->string('author')->nullable();
            $table->string('gender')->nullable();
            $table->integer('extent')->nullable();
            $table->date('edition')->nullable();
            $table->string('isbn')->nullable();
            $table->string('container')->nullable();
            $table->string('signature')->nullable();
            $table->string('digital')->nullable();
            $table->string('origin')->nullable();
            $table->date('date_added')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->date('discharge_date')->nullable();
            $table->string('discharge_reason')->nullable();
            $table->text('synopsis')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

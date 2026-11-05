<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_enrollment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pre_enrollment_id')->nullable()->constrained('pre_enrollments')->nullOnDelete();
            $table->unsignedSmallInteger('cycle_id');
            $table->string('doc_type', 10)->default('DNI');
            $table->string('doc_number', 20);
            $table->string('payer_name', 150);
            $table->string('career_name', 150)->nullable();
            $table->enum('type', ['charge', 'refund'])->default('charge');
            $table->foreignId('related_payment_id')->nullable()->constrained('pre_enrollment_payments')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50)->default('cash');
            $table->string('concept', 255);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cycle_id', 'doc_number']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_enrollment_payments');
    }
};

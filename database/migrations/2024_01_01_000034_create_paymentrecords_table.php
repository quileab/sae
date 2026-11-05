<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paymentrecords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('transaction_id')->nullable()->index();
            $table->foreignId('userpayments_id')->constrained('userpayments');
            $table->foreignId('payment_invoice_id')->nullable()->constrained('payment_invoices')->nullOnDelete();
            $table->string('paymentBox');
            $table->string('description');
            $table->decimal('paymentAmount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paymentrecords');
    }
};

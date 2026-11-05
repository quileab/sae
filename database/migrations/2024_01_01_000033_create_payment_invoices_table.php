<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('cae', 20);
            $table->date('cae_expiration');
            $table->string('voucher_type', 10);
            $table->string('point_of_sales', 10);
            $table->string('voucher_number', 20);
            $table->date('issue_date');
            $table->decimal('amount', 10, 2);
            $table->json('afip_response');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_invoices');
    }
};

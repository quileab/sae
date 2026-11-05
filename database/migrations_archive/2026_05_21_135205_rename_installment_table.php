<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('installment') && ! Schema::hasTable('installments')) {
            Schema::rename('installment', 'installments');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('installments') && ! Schema::hasTable('installment')) {
            Schema::rename('installments', 'installment');
        }
    }
};

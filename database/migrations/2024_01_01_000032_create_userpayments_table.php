<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('userpayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->date('date')->index();
            $table->string('title');
            $table->decimal('paid', 10, 2)->default(0.00);
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index(['user_id', 'date'], 'userpayments_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('userpayments');
    }
};

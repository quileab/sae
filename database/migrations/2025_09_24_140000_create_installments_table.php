<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstallmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->primary(['user_id', 'type_id', 'point_id', 'number_id']);
            $table->foreignId('user_id')->constrained();
            $table->string('type_id')->key();
            $table->unsignedSmallInteger('point_id')->key();
            $table->unsignedInteger('number_id')->key();
            $table->tinyText('text');
            $table->decimal('debit', 10, 2)->default(0);
            $table->decimal('credit', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('installments');
    }
}

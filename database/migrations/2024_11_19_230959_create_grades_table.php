<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->string('id', 30);
            $table->string('group', 30);
            $table->string('description', 250);
            $table->string('type', 5); // text, bool, int, radio, check, csv
            $table->string('value', 250);
            $table->timestamps();
            $table->primary('id');
            $table->index('group')->unique();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('configs');
    }
}
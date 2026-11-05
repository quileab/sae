<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configs', function (Blueprint $table) {
            $table->string('id', 30);
            $table->string('group', 30);
            $table->string('description', 250);
            $table->string('type', 10);
            $table->string('value', 250);
            $table->timestamps();

            $table->primary('id');
            $table->index('group', 'configs_group_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configs');
    }
};

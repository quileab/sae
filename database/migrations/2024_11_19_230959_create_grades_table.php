<?php

use App\Models\User;
use App\Models\ClassSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(ClassSession::class)->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('grade')->default(0);
            $table->boolean('approved')->default(false);
            $table->unsignedTinyInteger('attendance')->default(0);
            $table->text('comments', 255)->nullable();
            $table->timestamps();

            $table->unique(['class_session_id', 'user_id'], 'unique_class_session_user');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grades');
    }
}

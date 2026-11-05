<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand role enum to include tutor and other roles if using MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student','teacher','director','administrative','treasurer','user','preceptor','principal','basic_user','tutor') NOT NULL DEFAULT 'user'");
        }

        Schema::create('student_tutor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tutor_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship', 50)->default('tutor');
            $table->boolean('is_emergency_contact')->default(true);
            $table->boolean('is_authorized_pickup')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'tutor_id']);
            $table->index('student_id');
            $table->index('tutor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tutor');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','student','teacher','director','administrative','treasurer','user','preceptor') NOT NULL DEFAULT 'user'");
        }
    }
};

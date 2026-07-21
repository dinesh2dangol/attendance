<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->string('employee_name', 50)->nullable();
            $table->dateTime('join_date_eng')->nullable();
            $table->string('join_date_npt', 20)->nullable();
            $table->string('photo', 255)->nullable();
            $table->integer('status')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('working_hours', 6, 2)->nullable();
            $table->boolean('part_time')->nullable();
            $table->integer('department_id')->nullable();
            $table->string('gender', 8)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

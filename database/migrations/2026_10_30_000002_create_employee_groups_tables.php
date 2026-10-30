<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->foreignId('supervisor_employee_id')
                ->unique()
                ->constrained('employees')
                ->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_group_members', function (Blueprint $table) {
            $table->foreignId('employee_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->primary(['employee_group_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_group_members');
        Schema::dropIfExists('employee_groups');
    }
};

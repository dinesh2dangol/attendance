<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_groups', function (Blueprint $table) {
            $table->index('supervisor_employee_id', 'employee_groups_supervisor_employee_id_index');
            $table->dropUnique('employee_groups_supervisor_employee_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employee_groups', function (Blueprint $table) {
            $table->dropIndex('employee_groups_supervisor_employee_id_index');
            $table->unique('supervisor_employee_id');
        });
    }
};

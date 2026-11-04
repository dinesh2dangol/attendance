<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supervisor_attendance_punch_selections', 'remarks')) {
            Schema::table('supervisor_attendance_punch_selections', function (Blueprint $table) {
                $table->text('remarks')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supervisor_attendance_punch_selections', 'remarks')) {
            Schema::table('supervisor_attendance_punch_selections', function (Blueprint $table) {
                $table->dropColumn('remarks');
            });
        }
    }
};

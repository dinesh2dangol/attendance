<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supervisor_attendance_punch_selections')) {
            Schema::create('supervisor_attendance_punch_selections', function (Blueprint $table) {
                $table->id();
                $table->string('user_id');
                $table->date('attendance_date');
                $table->unsignedTinyInteger('check_in_punch')->nullable();
                $table->unsignedTinyInteger('lunch_out_punch')->nullable();
                $table->unsignedTinyInteger('lunch_in_punch')->nullable();
                $table->unsignedTinyInteger('check_out_punch')->nullable();
                $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('supervisor_attendance_punch_selections', function (Blueprint $table) {
            $table->unique(['user_id', 'attendance_date'], 'sup_att_punch_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_attendance_punch_selections');
    }
};

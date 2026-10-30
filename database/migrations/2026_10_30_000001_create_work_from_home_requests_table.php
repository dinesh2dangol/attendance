<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_from_home_requests', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason', 255);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'start_date', 'end_date'], 'wfh_user_status_dates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_from_home_requests');
    }
};

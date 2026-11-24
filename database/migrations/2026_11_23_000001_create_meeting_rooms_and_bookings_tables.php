<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('location', 150)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('meeting_room_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_room_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('booked_by_name');
            $table->string('title', 150);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_room_id', 'starts_at', 'ends_at'], 'meeting_room_bookings_room_time_index');
            $table->index(['user_id', 'starts_at'], 'meeting_room_bookings_user_start_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_room_bookings');
        Schema::dropIfExists('meeting_rooms');
    }
};

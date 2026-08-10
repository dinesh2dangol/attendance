<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // If the project already has a legacy `leaves` table, avoid creating duplicate.
        if (Schema::hasTable('leaves')) {
            return;
        }

        Schema::create('leaves', function (Blueprint $table) {
            // Use a compatible legacy-like schema as fallback
            $table->increments('leave_id');
            $table->string('user_id', 50)->nullable();
            $table->date('leave_date');
            $table->string('leave_type', 50);
            $table->string('leave_description', 255)->nullable();
            $table->integer('approval_status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('leaves')) {
            Schema::dropIfExists('leaves');
        }
    }
};

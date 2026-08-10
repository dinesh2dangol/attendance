<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('leaves') && Schema::hasColumn('leaves', 'leave_description')) {
            DB::statement('ALTER TABLE `leaves` MODIFY COLUMN `leave_description` TEXT NULL');
        }
    }

    public function down()
    {
        if (Schema::hasTable('leaves') && Schema::hasColumn('leaves', 'leave_description')) {
            DB::statement('ALTER TABLE `leaves` MODIFY COLUMN `leave_description` VARCHAR(255) NULL');
        }
    }
};

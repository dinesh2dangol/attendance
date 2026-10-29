<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ManualAttendance') || ! Schema::hasColumn('ManualAttendance', 'category')) {
            return;
        }

        DB::table('ManualAttendance')
            ->whereNull('category')
            ->whereIn('remarks', [
                'Remote work (Election)',
                'Remote Work (Pokhara)',
                'WFH',
                'WFH (Grandfather demise)',
                'WFH (Annotation work with Khagendra, Bishwa, Robish and Sachin sir)',
                'Work from home (solve organization issue in production server for Maithili Annotation work)',
                'WFH because of muscle pain',
            ])
            ->update(['category' => 'WFH']);

        DB::table('ManualAttendance')
            ->whereNull('category')
            ->where('remarks', 'Fingerprint not registered')
            ->update(['category' => 'Others']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ManualAttendance') || ! Schema::hasColumn('ManualAttendance', 'category')) {
            return;
        }

        DB::table('ManualAttendance')
            ->where('category', 'WFH')
            ->whereIn('remarks', [
                'Remote work (Election)',
                'Remote Work (Pokhara)',
                'WFH',
                'WFH (Grandfather demise)',
                'WFH (Annotation work with Khagendra, Bishwa, Robish and Sachin sir)',
                'Work from home (solve organization issue in production server for Maithili Annotation work)',
                'WFH because of muscle pain',
            ])
            ->update(['category' => null]);

        DB::table('ManualAttendance')
            ->where('category', 'Others')
            ->where('remarks', 'Fingerprint not registered')
            ->update(['category' => null]);
    }
};

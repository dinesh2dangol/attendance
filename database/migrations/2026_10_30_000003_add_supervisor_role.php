<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'supervisor'],
            ['name' => 'Supervisor', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        $supervisorRoleId = DB::table('roles')->where('slug', 'supervisor')->value('id');
        $employeeRoleId = DB::table('roles')->where('slug', 'employee')->value('id');

        if ($supervisorRoleId && $employeeRoleId) {
            DB::table('users')->where('role_id', $supervisorRoleId)->update(['role_id' => $employeeRoleId]);
        }

        DB::table('roles')->where('slug', 'supervisor')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->upsert([
            ['name' => 'Administrator', 'slug' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Employee', 'slug' => 'employee', 'created_at' => now(), 'updated_at' => now()],
        ], ['slug'], ['name', 'updated_at']);

        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        DB::table('users')->whereNull('role_id')->update(['role_id' => $adminRoleId]);
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('slug', ['admin', 'employee'])->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('access_role', 30)->default('none'));
        DB::table('users')->update(['access_role' => 'admin']);
        $email = 'info@hemingway-hilden.de';
        if (! DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            // No shared or published initial password. Set one through password reset or users:info.
            DB::table('users')->insert(['name' => 'Hemingway Team', 'email' => $email,
                'password' => Hash::make(Str::random(64)), 'access_role' => 'operations',
                'is_info_admin' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->update(['access_role' => 'operations', 'is_info_admin' => false]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('access_role'));
    }
};

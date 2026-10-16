<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The office's admin account, in the database itself (no .env needed). Runs once, on the first request after
 * the update. Only the password's hash is here; change the password from "My account" after signing in.
 */
return new class extends Migration
{
    private const EMAIL = 'info@maharatnet.com';

    private const PASSWORD_HASH = '$2y$12$G/afN0DilueQ3d4Ngft.nu.rpic4cq6hUR0cpQErgeH0F8na0IlVK';

    public function up(): void
    {
        $now = now();
        $row = ['name' => 'Maharat Admin', 'password' => self::PASSWORD_HASH, 'role' => 'admin', 'active' => true, 'updated_at' => $now];
        if (DB::table('users')->where('email', self::EMAIL)->exists()) {
            DB::table('users')->where('email', self::EMAIL)->update($row);
        } else {
            DB::table('users')->insert($row + ['email' => self::EMAIL, 'created_at' => $now]);
        }
    }

    public function down(): void {}
};

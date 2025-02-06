<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateUserPasswordsSeeder extends Seeder
{
    public function run()
    {
        // Ambil semua pengguna dari tabel user
        $users = DB::table('user')->get();

        foreach ($users as $user) {
            // Hash password yang ada dan simpan kembali ke database
            DB::table('user')
                ->where('user_id', $user->user_id)
                ->update([
                    'password' => Hash::make($user->password)
                ]);
        }
    }
}

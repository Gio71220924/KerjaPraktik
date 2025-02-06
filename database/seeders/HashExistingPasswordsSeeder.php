<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HashExistingPasswordsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ambil semua user dari tabel 'user'
        $users = DB::table('user')->get();

        foreach ($users as $user) {
            // Langsung hash password tanpa cek apakah sudah di-hash atau belum
            DB::table('user')
                ->where('user_id', $user->user_id)
                ->update([
                    'password' => Hash::make($user->password) // Hash password
                ]);
        }
    }
}

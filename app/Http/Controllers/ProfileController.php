<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ProfileController extends Controller
{
    public function edit()
    {
        // Ambil data pengguna yang sedang login
        $user = Auth::user();
        return view('admin.profile', compact('user'));
    }

    public function update(Request $request)
    {
        // Validasi input
        $request->validate([
            'username' => 'required|',
            'password' => 'required',
        ]);

        // Ambil data pengguna yang sedang login
        $user = Auth::user();

        // Update username
        $user->username = $request->username;

        // Update password jika diisi
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        // Simpan perubahan
        $user->save();

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully!');
    }
}


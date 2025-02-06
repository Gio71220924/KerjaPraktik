<?php

namespace App\Http\Controllers;

use App\Models\Kasus;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //menampilkan form regis
    function showRegistrationForm() {
        return view('auth.registration');
    }

    //proses submit regis
    function registration(Request $request) {
        
        //input ga boleh kosong
        $request->validate([
            'username' => 'required|string|max:30|unique:user', // Memastikan username tidak lebih dari 30 karakter dan unik
            'password' => 'required|string|max:6',
            'case_title' => 'required|string|max:255',
            'case_desc' => 'required|string|max:255' // Memastikan password minimal 8 karakter
        ]);
        
        //proses input
        $user = new User();
        $user->username = $request-> username;
        $user->password = bcrypt($request-> password);
        $user->save();

        //proses input
        $kasus = new Kasus();
        $kasus->case_title = $request->case_title; // Simpan case title
        $kasus->case_desc = $request->case_desc;   // Simpan case description
        $kasus->case_num = $user->user_id;          // Set case_num sama dengan user_id
        $kasus->save(); // Simpan ke tabel kasus
        
        // //message flash
        // session()->flash('success', 'congratulations! your account has been created.');
        
        return redirect()->route('login');
    }


    // Menampilkan form login
    function showLoginForm()
    {
        return view('auth.login');
    }

    
    function showLoginAdminForm()
    {
        return view('auth.loginAdmin');
    }


    // Proses login
    function login(Request $request)
    {
        // Validasi input
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Cari user berdasarkan username
        $user = User::where('username', $request->username)->first();

        // Cek apakah user ditemukan dan password cocok
        if ($user && Hash::check($request->password, $user->password)) {
            // Cek apakah user adalah admin
            if ($user->role === 'user') {
                if ($user->active === 'Inactive') {
                    return back()->withErrors(['username' => 'Your account is inactive. Please contact admin.']);
                }
                // Login user
                Auth::login($user);

                // Redirect ke halaman dashboard atau tujuan lain
                return view('admin.menu.case');
                
            } 
            
            else {
                // Jika user bukan admin, kembali ke login dengan pesan error
                return redirect()->back()->withErrors(['role' => 'Access denied. User only.']);
            }
        } else {
            // Jika tidak cocok, kembali ke form login dengan pesan error
            return redirect()->back()->withErrors(['username' => 'Incorrect username or password.']);
        }
    }


    function loginAdmin(Request $request)
    {
        // Validasi input
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        // Cari user berdasarkan username
        $user = User::where('username', $request->username)->first();

        // Cek apakah user ditemukan dan password cocok
        if ($user && Hash::check($request->password, $user->password)) {
            // Cek apakah user adalah admin
            if ($user->role === 'admin') {
                // Login user
                Auth::login($user);

                // Redirect ke halaman dashboard atau tujuan lain
                return view('user.menu.user');
            } else {
                // Jika user bukan admin, kembali ke login dengan pesan error
                return redirect()->back()->withErrors(['role' => 'Access denied. Admin only.']);
            }
        } else {
            // Jika username atau password salah
            return redirect()->back()->withErrors(['username' => 'Incorrect username or password.']);
        }
    }

    // Logout user
    function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }

    public function updateProject(Request $request, $id) {
        // Validasi input
        $request->validate([
            'case_title' => 'required|string|max:255',
            'case_desc' => 'required|string|max:255',
        ]);
    
        // Ambil data kasus berdasarkan ID
        $kasus = Kasus::find($id);
    
        // Pastikan $kasus bukan null
        if (!$kasus) {
            return redirect()->back()->withErrors(['case' => 'Project not found.']);
        }
    
        // Proses update
        $kasus->case_title = $request->case_title;
        $kasus->case_desc = $request->case_desc;
        $kasus->save(); // Simpan perubahan ke database
    
        return redirect()->back()->with('success', 'Project updated successfully!');
    }
    
}

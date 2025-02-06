<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Atribut;
use App\Models\AtributValue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class AtributController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Pastikan pengguna sudah login
        if (Auth::check()) {
            // Ambil user_id dari user yang sedang login
            $user_id = Auth::user()->user_id;
            // Ambil user_id dari user yang sedang login
            $username = Auth::user()->username;
            // Ambil data atribut berdasarkan user_id yang sedang login
            $atribut = Atribut::where('user_id', $user_id)->get();
            $kasus = \App\Models\Kasus::where('case_num', $user_id)->first();

            // Kirim data atribut ke view
            return view('admin.menu.attributte', compact('atribut', 'kasus', 'username')); // Menambahkan user_id
        }

        // Redirect jika user tidak login
        return redirect()->route('login'); // Ganti 'login' dengan nama route login jika perlu
    
    }

    // Menampilkan form untuk menambah atribut
    public function create()
    {
        // Ambil user_id dari user yang sedang login
        $user_id = Auth::user()->user_id;

        // Ambil daftar kasus berdasarkan user_id yang sedang login
        $cases = \App\Models\Kasus::where('case_num', $user_id)->get();

        return view('admin.menu.attributteTambah', compact('cases'));
    }

    // Menyimpan data atribut baru
    public function store(Request $request)
    {
        $request->validate([
            'atribut_name' => [
                'required', 'string', 'max:200', 'regex:/^[^\s]+$/',
                Rule::unique('atribut', 'atribut_name')->where(function ($query) use ($request) {
                    return $query->where('user_id', Auth::user()->user_id); // Pastikan unik per user
                })
            ],
            'goal' => 'required|in:T,F',
            'atribut_desc' => 'nullable|string|max:250',
        ]);
        
        // Pastikan tidak ada atribut lain dengan goal 'T' jika goal yang baru adalah 'T'
        if ($request->goal === 'T') {
            $existingGoal = Atribut::where('goal', 'T')
                ->where('user_id', Auth::user()->user_id) // Cek hanya dalam user yang sama
                ->exists(); 
        
            if ($existingGoal) {
                return back()->withErrors(['goal' => 'Attribute with true goal already exists.'])->withInput();
            }
        }
        
        Atribut::create([
            'atribut_name' => $request->atribut_name,
            'goal' => $request->goal,
            'atribut_desc' => $request->atribut_desc,
            'user_id' => Auth::user()->user_id, // Menyimpan user_id yang sedang login
            'case_num' => Auth::user()->user_id, // Menggunakan user_id sebagai case_num
        ]);

        return redirect()->route('admin.menu.attributte')->with('success', 'New attribute added successfully.');
    }

    // Menampilkan form untuk mengedit atribut
    public function edit($id)
    {
        $atribut = Atribut::findOrFail($id);
        return view('admin.menu.attributteEdit', compact('atribut'));
    }

    // Mengupdate data atribut
    public function update(Request $request, $id)
    {
        $request->validate([
            'atribut_name' => [
                'required', 'string', 'max:200', 'regex:/^[^\s]+$/',
                Rule::unique('atribut', 'atribut_name')->where(function ($query) use ($request) {
                    return $query->where('user_id', Auth::user()->user_id); // Pastikan unik per user
                })
            ],
            'goal' => 'required|in:T,F',
            'atribut_desc' => 'nullable|string|max:250',
        ]);
        
        // Pastikan tidak ada atribut lain dengan goal 'T' jika goal yang baru adalah 'T'
        if ($request->goal === 'T') {
            $existingGoal = Atribut::where('goal', 'T')
                ->where('user_id', Auth::user()->user_id) // Cek hanya dalam user yang sama
                ->exists(); 
        
            if ($existingGoal) {
                return back()->withErrors(['goal' => 'Attribute with true goal already exists.'])->withInput();
            }
        }

        $atribut = Atribut::findOrFail($id);
        $atribut->update($request->only(['atribut_name', 'goal', 'atribut_desc']));

        return redirect()->route('admin.menu.attributte')->with('success', 'Attribute updated successfully.');
    }

    // Menghapus atribut
    public function destroy($id)
    {
        $atribut = Atribut::findOrFail($id);
        // Memeriksa apakah ada atribut_value yang terkait dengan atribut ini
        $atributValueCount = AtributValue::where('atribut_id', $id)->count();

        // Jika ada atribut_value yang terkait, jangan hapus atribut dan tampilkan pesan error
        if ($atributValueCount > 0) {
            return redirect()->route('admin.menu.attributte')->withErrors('Cannot delete this attribute because there are associated attribute values.');
        }
        $atribut->delete();

        return redirect()->route('admin.menu.attributte')->with('success', 'Attribute successfully deleted.');
    }

    
}

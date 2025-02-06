<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\AtributValue;
use Illuminate\Http\Request;

class AtributValueController extends Controller
{
    public function index()
    {
        // Pastikan pengguna sudah login
        if (Auth::check()) {
            // Ambil user_id dari user yang sedang login
            $user_id = Auth::user()->user_id;
            // Ambil user_id dari user yang sedang login
            $username = Auth::user()->username;
            // Ambil data atribut_value berdasarkan user_id yang sedang login
            $atributValues = AtributValue::where('user_id', $user_id)->get();
            $kasus = \App\Models\Kasus::where('case_num', $user_id)->first();

            // Kirim data ke view
            return view('admin.menu.attributteValue', compact('atributValues', 'kasus', 'username'));
        }
    
        // Redirect jika user tidak login
        return redirect()->route('login');
    }

    public function create()
    {
        // Ambil user_id dari user yang sedang login
        $user_id = Auth::user()->user_id;

        $cases = \App\Models\Kasus::where('case_num', $user_id)->get();

        // Ambil semua atribut untuk dropdown pilihan atribut_name
        $atributs = \App\Models\Atribut::where('user_id', $user_id)->get();

        return view('admin.menu.attributteValueTambah', compact('atributs', 'cases'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'atribut_id' => 'required|exists:atribut,atribut_id',
            'value_name' => 'required|string|max:200|unique:atribut_value,value_name,NULL,NULL,atribut_id,' . $request->atribut_id,
            'value_desc' => 'nullable|string|max:250',
        ]);

        // Menyimpan data atribut value baru
        AtributValue::create([
            'atribut_id' => $request->atribut_id,
            'value_name' => $request->value_name,
            'value_desc' => $request->value_desc,
            'user_id' => Auth::user()->user_id, // Menyimpan user_id yang sedang login
            'case_num' => Auth::user()->user_id, // Menggunakan user_id sebagai case_num
        ]);

        return redirect()->route('admin.menu.attributteValue')->with('success', 'New attribute value added successfully');
    }

    public function edit($id)
{
    // Ambil atribut berdasarkan ID dan pastikan user_id sama
    $atribut = AtributValue::where('value_id', $id)
                            ->where('user_id', Auth::user()->user_id) // Pastikan user_id sama
                            ->firstOrFail();

    // dd($atribut);
    // Ambil semua atribut untuk ditampilkan di dropdown
    $atributs = \App\Models\Atribut::where('user_id', Auth::user()->user_id)->get();
    
    return view('admin.menu.attributteValueEdit', compact('atribut', 'atributs'));
}

    // Mengupdate data atribut
    public function update(Request $request, $id)
    {
        $request->validate([
            'atribut_id' => 'required|exists:atribut,atribut_id',
            'value_name' => 'required|string|max:200|unique:atribut_value,value_name,NULL,NULL,atribut_id,' . $request->atribut_id,
            'value_desc' => 'nullable|string|max:250',
        ]);

         // Ambil atribut yang akan diupdate
        $atribut = AtributValue::where('value_id', $id)
                ->where('user_id', Auth::user()->user_id) // Pastikan user_id sama
                ->firstOrFail();

        $atribut->update($request->only(['atribut_id', 'value_name', 'value_desc']));

        return redirect()->route('admin.menu.attributteValue')->with('success', 'Attribute value updated successfully.');
    }

    // Menghapus atribut
    public function destroy($id)
{
    // Ambil atribut berdasarkan ID dan pastikan user_id sama
    $atribut = AtributValue::where('value_id', $id)
                            ->where('user_id', Auth::user()->user_id) // Pastikan user_id sama
                            ->firstOrFail();

    // Hapus atribut
    $atribut->delete();

    return redirect()->route('admin.menu.attributteValue')->with('success', 'Attribute value successfully deleted.');
}
}

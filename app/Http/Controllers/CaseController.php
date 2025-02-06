<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Kasus;

class CaseController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        $kasus = Kasus::where('case_num', $user->user_id)->first();

        return view('admin.menu.caseEdit', compact('user', 'kasus'));
    }

    
    public function update(Request $request)
    {
        $request->validate([
            'case_title' => 'required|string|max:255',
            'case_desc' => 'nullable|string',
        ]);

        $user = Auth::user();
        $kasus = Kasus::where('case_num', $user->user_id)->first();

        if ($kasus) {
            // Melakukan update data kasus
            $kasus->update([
                'case_title' => $request->case_title,
                'case_desc' => $request->case_desc,
            ]);

            // Menambahkan pesan sukses setelah data berhasil diupdate
            return view ('admin.menu.case', compact('user', 'kasus'))->with('success', 'Case updated successfully!');
        }
    }

}

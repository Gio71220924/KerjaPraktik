<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SVMController extends Controller
{
    public function show()
    {
        $user_id = Auth::id();
        $tableName = 'svm_user_' . $user_id;

        // Cek apakah tabel ada
        if (!Schema::hasTable($tableName)) {
            // Tabel tidak tersedia
            $svmData = [];
            $message = 'SVM data not found. Please click the Generate SVM button.';
        } else {
            // Ambil data dari tabel
            $svmDataRaw = DB::table($tableName)->get();

            if ($svmDataRaw->isEmpty()) {
                // Tabel ada tapi kosong
                $svmData = [];
                $message = 'SVM data not found. Please click the Generate SVM button.';
            } else {
                // Proses data menjadi array yang dapat digunakan di view
                $svmData = $svmDataRaw->toArray();
                $message = null; // Tidak ada pesan jika data tersedia
            }
        }

        return view('admin.menu.svm', compact('svmData', 'message'));
    }

    // public function generateSVM()
    // {
    //     $user_id = Auth::id();
    //     $case_num = $user_id;

    //     // Jalankan script untuk menghasilkan SVM
    //     $command = 'php "' . base_path('scripts/svm/svm.php') . '" ' . $user_id . ' ' . $case_num;
    //     shell_exec($command);

    //     return redirect()->route('SVM.show')->with('success', 'SVM generated successfully.');
    // }
    
}

    
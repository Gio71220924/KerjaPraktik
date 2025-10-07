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

        if (!Schema::hasTable($tableName)) {
            $svmData = [];
            $message = 'SVM data not found. Please click the "Generate SVM" button.';
        } else {
            $svmDataRaw = DB::table($tableName)->get();
            if ($svmDataRaw->isEmpty()) {
                $svmData = [];
                $message = 'No SVM data available. Please generate the model first.';
            } else {
                $svmData = $svmDataRaw->toArray();
                $message = null;
            }
        }

        return view('admin.menu.svm', compact('svmData', 'message'));
    }

    public function generateSVM()
    {
        $user_id = Auth::id();
        $case_num = $user_id; // optional: bisa diubah nanti sesuai ID case terakhir

        // Jalankan script PHP untuk melatih SVM
        $command = 'php "' . base_path('app/scripts/decision-tree/svm.php') . '" ' . $user_id . ' ' . $case_num;
        $output = shell_exec($command);

        // Simpan hasil ke tabel log user (opsional)
        $tableName = 'svm_user_' . $user_id;
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function ($table) {
                $table->id();
                $table->string('status');
                $table->text('output')->nullable();
                $table->timestamps();
            });
        }

        DB::table($tableName)->insert([
            'status' => 'SVM generated',
            'output' => $output,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('SVM.show')->with('success', 'SVM generated successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SVMController extends Controller
{
    public function show()
    {
        $userId = Auth::id();
        if (!$userId) return redirect()->route('login');

        $table = 'svm_user_' . $userId;

        if (!Schema::hasTable($table)) {
            return view('admin.menu.svm', [
                'svmData' => collect(),
                'message' => 'SVM data not found. Please click the "Generate SVM" button.',
            ]);
        }

        // Build SELECT yang robust ke skema lama/baru
        $selects = ['id', 'status'];

        if (Schema::hasColumn($table, 'execution_time')) {
            $selects[] = 'execution_time';
        } elseif (Schema::hasColumn($table, 'waktu')) {
            $selects[] = 'waktu AS execution_time';
        } else {
            $selects[] = 'NULL AS execution_time';
        }

        $selects[] = Schema::hasColumn($table, 'model_path') ? 'model_path' : 'NULL AS model_path';
        $selects[] = Schema::hasColumn($table, 'output')     ? 'output'     : 'NULL AS output';
        $selects[] = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'NULL AS created_at';

        $orderCol = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id';

        $svmData = DB::table($table)
            ->selectRaw(implode(", ", $selects))
            ->orderBy($orderCol, 'asc')
            ->get();

        return view('admin.menu.svm', [
            'svmData' => $svmData,
            'message' => $svmData->isEmpty() ? 'No SVM data available. Please generate the model first.' : null,
        ]);
    }

    public function generateSVM()
    {
        $userId = Auth::id();
        if (!$userId) return redirect()->route('login');

        // PHP CLI dari .env (fallback ke PHP yang sedang jalan)
        $php = env('PHP_CLI', PHP_BINARY);

        // Pastikan path skrip BENAR (bukan app/scripts/…)
        $script = base_path('scripts/decision-tree/SVM.php');
        if (!file_exists($script)) {
            return redirect()->route('SVM.show')
                ->with('error', "Script not found: {$script}");
        }

        $caseNum = $userId;

        // Quote aman + gabungkan stderr ke stdout
        $cmd = '"' . $php . '" ' 
             . escapeshellarg($script) . ' '
             . escapeshellarg((string)$userId) . ' '
             . escapeshellarg((string)$caseNum)
             . ' 2>&1';

        $output = shell_exec($cmd) ?? '';

        // Coba ambil JSON di akhir output
        $summary = null;
        if (preg_match('/(\{.*\})\s*$/s', $output, $m)) {
            $decoded = json_decode($m[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $summary = $decoded;
            }
        }

        // Siapkan tabel log jika belum ada (supaya bisa catat error dari controller)
        $table = 'svm_user_' . $userId;
        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('status', 32);
                $t->decimal('execution_time', 12, 6)->nullable();
                $t->string('model_path', 1024)->nullable();
                $t->longText('output')->nullable();
                $t->timestamps();
            });
        } else {
            // Pastikan kolom minimal ada (robust pada skema lama)
            if (!Schema::hasColumn($table, 'execution_time')) {
                Schema::table($table, fn (Blueprint $t) => $t->decimal('execution_time', 12, 6)->nullable()->after('status'));
            }
            if (!Schema::hasColumn($table, 'model_path')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('model_path', 1024)->nullable()->after('execution_time'));
            }
            if (!Schema::hasColumn($table, 'output')) {
                Schema::table($table, fn (Blueprint $t) => $t->longText('output')->nullable()->after('model_path'));
            }
            if (!Schema::hasColumn($table, 'created_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->timestamps());
            }
        }

        // Jika training sukses, SVM.php sudah menulis log sendiri.
        // Kalau gagal, kita catat error output di sini agar terlihat di UI.
        if (!is_array($summary) || ($summary['status'] ?? 'error') !== 'success') {
            DB::table($table)->insert([
                'status'         => 'error',
                'execution_time' => null,
                'model_path'     => null,
                'output'         => $output,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            return redirect()->route('SVM.show')->with('error', 'SVM training failed. Check the log table for details.');
        }

        // Sukses → tampilkan ringkasan (tanpa menambah duplikat log)
        return redirect()
            ->route('SVM.show')
            ->with('success', 'SVM model generated successfully.')
            ->with('svm_summary', json_encode($summary, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    }
}

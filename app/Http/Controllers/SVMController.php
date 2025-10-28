<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class SVMController extends Controller {
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

        // Susun select yang kompatibel ke belakang
        $selects = ['id'];

        // status -> success|error (termasuk log lama "SVM trained ...")
        if (Schema::hasColumn($table, 'status')) {
            $selects[] = "CASE
                WHEN status IN ('success','error') THEN status
                WHEN status LIKE 'SVM trained%' THEN 'success'
                WHEN status LIKE 'trained%' THEN 'success'
                ELSE 'error'
            END AS status";
        } else {
            $selects[] = "'error' AS status";
        }

        // execution_time: pakai waktu kalau ada, else execution_time, else NULL
        if (Schema::hasColumn($table, 'waktu')) {
            $selects[] = "waktu AS execution_time";
        } elseif (Schema::hasColumn($table, 'execution_time')) {
            $selects[] = "execution_time";
        } else {
            $selects[] = "NULL AS execution_time";
        }

        $selects[] = Schema::hasColumn($table, 'model_path') ? 'model_path' : 'NULL AS model_path';
        $selects[] = Schema::hasColumn($table, 'output')     ? 'output'     : 'NULL AS output';
        $selects[] = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'NULL AS created_at';

        $svmData = DB::table($table)
            ->selectRaw(implode(",\n", $selects))
            ->orderByDesc(Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id')
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

    $caseNum = $userId; // mengikuti pola kamu
    $process = new Process([
        PHP_BINARY,
        base_path('scripts/decision-tree/SVM.php'),
        (string) $userId,
        (string) $caseNum,
    ]);
    $process->run();

    $stdout = trim($process->getOutput());
    $stderr = trim($process->getErrorOutput());
    $combined = $stderr !== '' ? trim($stdout . PHP_EOL . $stderr) : $stdout;

    $summary = null;
    $logOutput = $combined;

    if (preg_match('/(\{.*\})$/s', $combined, $m)) {
        $decoded = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $summary = $decoded;
            $logOutput = trim(substr($combined, 0, -strlen($m[1])));
        }
    }

    $table = 'svm_user_' . $userId;
    if (!Schema::hasTable($table)) {
        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->string('status', 50);
            $table->decimal('execution_time', 12, 6)->nullable();
            $table->string('model_path', 1024)->nullable();
            $table->longText('output')->nullable();
            $table->timestamps();
        });
    }

    // 🔧 kunci perbaikan: pakai duration jika execution_time tidak ada
    $executionTime = null;
    if (is_array($summary)) {
        $executionTime = $summary['execution_time'] ?? $summary['duration'] ?? null;
    }

    DB::table($table)->insert([
        'status'         => $summary['status'] ?? ($process->isSuccessful() ? 'success' : 'error'),
        'execution_time' => $executionTime,
        'model_path'     => $summary['model_path'] ?? null,
        'output'         => $logOutput !== '' ? $logOutput : null,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);

    if (!$process->isSuccessful()) {
        $msg = $summary['message'] ?? ($logOutput !== '' ? $logOutput : 'Failed to generate the SVM model.');
        return redirect()->route('SVM.show')->with('error', $msg);
    }

    return redirect()->route('SVM.show')->with('success', 'SVM model generated successfully.');
}
}
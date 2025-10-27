<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class SVMController extends Controller
{
    public function show()
    {
        $userId = Auth::id();

        if (!$userId) {
            return redirect()->route('login');
        }

        $tableName = 'svm_user_' . $userId;
        $svmData = collect();
        $message = null;

        if (!Schema::hasTable($tableName)) {
            $message = 'SVM data not found. Please click the "Generate SVM" button.';
        } else {
            $svmData = DB::table($tableName)->orderByDesc('created_at')->get();

            if ($svmData->isEmpty()) {
                $message = 'No SVM data available. Please generate the model first.';
            }
        }

        return view('admin.menu.svm', [
            'svmData' => $svmData,
            'message' => $message,
        ]);
    }

    public function generateSVM()
    {
        $userId = Auth::id();

        if (!$userId) {
            return redirect()->route('login');
        }

        $caseNum = $userId; // saat ini mengikuti pola algoritma lain
        $process = new Process([
            PHP_BINARY,
            base_path('scripts/decision-tree/SVM.php'),
            (string) $userId,
            (string) $caseNum,
        ]);

        $process->run();

        $outputCombined = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());

        if ($errorOutput !== '') {
            $outputCombined = trim($outputCombined . PHP_EOL . $errorOutput);
        }

        $summary = null;
        $logOutput = $outputCombined;

        if (preg_match('/(\{.*\})$/s', $outputCombined, $matches)) {
            $decoded = json_decode($matches[1], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $summary = $decoded;
                $logOutput = trim(substr($outputCombined, 0, -strlen($matches[1])));
            }
        }

        $tableName = 'svm_user_' . $userId;

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('status', 50);
                $table->decimal('execution_time', 12, 6)->nullable();
                $table->string('model_path', 1024)->nullable();
                $table->longText('output')->nullable();
                $table->timestamps();
            });
        }

        DB::table($tableName)->insert([
            'status' => $summary['status'] ?? ($process->isSuccessful() ? 'success' : 'error'),
            'execution_time' => $summary['execution_time'] ?? null,
            'model_path' => $summary['model_path'] ?? null,
            'output' => $logOutput !== '' ? $logOutput : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!$process->isSuccessful()) {
            $message = $summary['message'] ?? ($logOutput !== '' ? $logOutput : 'Failed to generate the SVM model.');

            return redirect()
                ->route('SVM.show')
                ->with('error', $message);
        }

        return redirect()
            ->route('SVM.show')
            ->with('success', 'SVM model generated successfully.');
    }
}

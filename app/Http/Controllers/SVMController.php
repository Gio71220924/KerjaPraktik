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
        $user_id = Auth::id();
        $tableName = 'svm_user_' . $user_id;

        $svmData = [];
        $message = null;

        if (!Schema::hasTable($tableName)) {
            $message = 'SVM data not found. Please click the "Generate SVM" button.';
        } else {
            $svmDataRaw = DB::table($tableName)->orderByDesc('created_at')->get();
            if ($svmDataRaw->isEmpty()) {
                $message = 'No SVM data available. Please generate the model first.';
            } else {
                $svmData = $svmDataRaw->toArray();
            }
        }

        return view('admin.menu.svm', compact('svmData', 'message'));
    }

    public function generateSVM()
    {
        $userId = Auth::id();

        if (!$userId) {
            return redirect()->route('SVM.show')->with('error', 'User tidak ditemukan atau belum masuk.');
        }

        $caseNum = $userId; // dapat disesuaikan apabila diperlukan

        $phpBinary = PHP_BINARY;
        $scriptPath = base_path('scripts/decision-tree/SVM.php');
        $process = new Process([$phpBinary, $scriptPath, (string) $userId, (string) $caseNum]);
        $process->setTimeout(300); // 5 menit
        $process->run();

        $rawOutput = trim($process->getOutput());
        $decodedOutput = json_decode($rawOutput, true);

        if (!$process->isSuccessful()) {
            $errorMessage = $decodedOutput['message'] ?? trim($process->getErrorOutput()) ?? 'Failed to execute SVM trainer.';

            return redirect()->route('SVM.show')->with('error', $errorMessage);
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedOutput)) {
            return redirect()->route('SVM.show')->with('error', 'Output pelatihan SVM tidak valid.');
        }

        $tableName = 'svm_user_' . $userId;
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('status');
                $table->double('duration')->nullable();
                $table->unsignedInteger('row_count')->nullable();
                $table->string('model_path')->nullable();
                $table->json('meta')->nullable();
                $table->longText('output')->nullable();
                $table->timestamps();
            });
        }

        DB::table($tableName)->insert([
            'status' => $decodedOutput['status'] ?? 'unknown',
            'duration' => $decodedOutput['duration'] ?? null,
            'row_count' => $decodedOutput['row_count'] ?? null,
            'model_path' => $decodedOutput['model_path'] ?? null,
            'meta' => json_encode($decodedOutput, JSON_UNESCAPED_SLASHES),
            'output' => $rawOutput,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('SVM.show')->with('success', $decodedOutput['message'] ?? 'SVM generated successfully.');
    }
}

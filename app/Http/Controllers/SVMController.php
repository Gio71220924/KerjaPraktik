<?php

namespace App\Http\Controllers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

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

        // SELECT kompatibel beberapa skema lama/baru
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

        // execution_time: alias dari 'waktu' jika ada
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

        $orderCol = Schema::hasColumn($table, 'created_at') ? 'created_at' : 'id';

        $svmData = DB::table($table)
            ->selectRaw(implode(",\n", $selects))
            ->orderByDesc($orderCol)
            ->get();

        return view('admin.menu.svm', [
            'svmData' => $svmData,
            'message' => $svmData->isEmpty() ? 'Belum ada data yang tersedia, silahkan Latih model terlebih dahulu' : null,
        ]);
    }

    public function generateSVM()
    {
        $userId = Auth::id();
        if (!$userId) return redirect()->route('login');

        $script  = base_path('scripts/decision-tree/SVM.php');
        if (!is_file($script)) {
            return redirect()->route('SVM.show')->with('error', "Script SVM tidak ditemukan: {$script}");
        }

        $caseNum = $userId;

        // Path interpreter
        $phpCgi = env('PHP_CGI', 'php-cgi'); // contoh: D:/xampp/php/php-cgi.exe
        $phpCli = env('PHP_CLI', 'php');     // contoh: D:/xampp/php/php.exe

        // ====== Jalankan via CGI (dengan -q agar header CGI hilang) ======
        $env = [
            'REDIRECT_STATUS' => '1',
            'REQUEST_METHOD'  => 'GET',
            'SCRIPT_FILENAME' => $script,
            'QUERY_STRING'    => http_build_query([
                'user_id'  => $userId,
                'case_num' => $caseNum,
            ]),
        ];

        // Pastikan php-cgi membaca php.ini yang benar (Windows)
        if (is_file($phpCgi)) {
            $env['PHPRC'] = dirname($phpCgi);
        }

        $process = new Process([$phpCgi, '-q'], null, $env);
        $process->setTimeout(3600);
        $process->setIdleTimeout(600);
        $process->run();

        $combined = $this->combineStdOutErr($process);
        [$summary, $logOutput] = $this->parseJsonTail($combined);

        // ====== Fallback ke CLI bila CGI gagal ======
        if (!$process->isSuccessful()) {
            $process = new Process([
                $phpCli,
                $script,
                (string) $userId,
                (string) $caseNum,
            ]);
            $process->setTimeout(3600)->setIdleTimeout(600);
            $process->run();

            $combined = $this->combineStdOutErr($process);
            [$summary, $logOutput] = $this->parseJsonTail($combined);
        }

        // ====== Pastikan tabel log per-user tersedia & kolom lengkap ======
        $table = 'svm_user_' . $userId;
        if (!Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('status', 50);
                $t->decimal('execution_time', 12, 6)->nullable();
                $t->string('model_path', 1024)->nullable();
                $t->longText('output')->nullable();
                $t->timestamps();
            });
        } else {
            if (!Schema::hasColumn($table, 'execution_time')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('execution_time', 12, 6)->nullable()->after('status');
                });
            }
            if (!Schema::hasColumn($table, 'model_path')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('model_path', 1024)->nullable()->after('execution_time');
                });
            }
            if (!Schema::hasColumn($table, 'output')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->longText('output')->nullable()->after('model_path');
                });
            }
            if (!Schema::hasColumn($table, 'created_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->timestamps();
                });
            }
        }

        // Map nilai waktu: execution_time atau duration
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

    /**
     * Gabungkan stdout + stderr menjadi satu string tertrim.
     */
    private function combineStdOutErr(Process $p): string
    {
        $stdout = trim($p->getOutput());
        $stderr = trim($p->getErrorOutput());
        return $stderr !== '' ? trim($stdout . PHP_EOL . $stderr) : $stdout;
    }

    /**
     * Ambil JSON paling akhir dari output (dengan mencari '{' terakhir).
     * @return array{0: array<string,mixed>|null, 1: string} [summary, logOutput]
     */
    private function parseJsonTail(string $combined): array
    {
        $summary   = null;
        $logOutput = $combined;

        $pos = strrpos($combined, '{');
        if ($pos !== false) {
            $candidate = substr($combined, $pos);
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $summary   = $decoded;
                $logOutput = trim(substr($combined, 0, $pos));
            }
        }

        return [$summary, $logOutput];
    }
}

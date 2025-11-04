<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ConsultationController extends Controller
{
    /**
     * Tampilkan daftar test_case_user_{userId}.
     */
    public function showConsultationForm()
    {
        $user = Auth::user();

        $generate = new \App\Models\Consultation();
        $generate->setTableForUser($user->user_id);

        $tableExists  = $generate->tableExists();
        $generateCase = $tableExists ? $generate->getRules() : collect();
        $columns      = $tableExists ? Schema::getColumnListing($generate->getTable()) : [];

        return view('admin.menu.testCase', compact('generateCase', 'columns', 'tableExists'));
    }

    /**
     * Form create test case (ambil atribut non-goal).
     */
    public function create()
    {
        $user_id = Auth::user()->user_id;

        $atributs = DB::table('atribut')
            ->where('user_id', $user_id)
            ->where(function ($q) {
                $q->where('goal', 'F')->orWhere('goal', 0)->orWhereNull('goal');
            })
            ->orderBy('goal', 'desc')
            ->get();

        return view('admin.menu.testCaseTambah', compact('atributs'));
    }

    /**
     * Simpan 1 baris test_case_user_{userId}, lalu jalankan algoritma sesuai action_type.
     * action_type ∈ {Matching Rule, Forward Chaining, Backward Chaining, Support Vector Machine}
     */
    public function store(Request $request)
    {
        $user_id = Auth::user()->user_id;
        $table   = 'test_case_user_' . $user_id;

        // Ambil atribut non-goal
        $atributs = DB::table('atribut')
            ->where('user_id', $user_id)
            ->where(function ($q) {
                $q->where('goal', 'F')->orWhere('goal', 0)->orWhereNull('goal');
            })
            ->orderBy('goal', 'desc')
            ->get();

        // Payload insert
        $data = [
            'user_id'   => $user_id,
            'case_num'  => $user_id,
            'algoritma' => $request->input('action_type'),
        ];

        // Isi kolom atribut_id_nama = "valueId_valueName"
        foreach ($atributs as $atribut) {
            $kolom = "{$atribut->atribut_id}_{$atribut->atribut_name}";
            if ($request->has($kolom)) {
                $input = $request->input($kolom);

                // Validasi value terhadap atribut_value
                $valid = DB::table('atribut_value')
                    ->where('user_id', $user_id)
                    ->where('atribut_id', $atribut->atribut_id)
                    ->where(DB::raw("CONCAT(value_id, '_', value_name)"), $input)
                    ->exists();

                if (!$valid) {
                    return back()->withErrors("Invalid value untuk atribut {$atribut->atribut_name}.");
                }
                $data[$kolom] = $input;
            }
        }

        if (count($data) <= 2) { // hanya user_id, case_num, algoritma → tak ada atribut terisi
            return back()->withErrors('Tidak ada nilai atribut yang diisi.');
        }

        if (!Schema::hasTable($table)) {
            return back()->withErrors("Tabel {$table} belum ada.");
        }

        // Insert baris test case
        DB::table($table)->insert($data);

        $actionType = $request->input('action_type');

        // === Matching Rule
        if ($actionType === 'Matching Rule') {
            return redirect()
                ->route('inference.generate', ['user_id' => $user_id, 'case_num' => $user_id])
                ->with('success', 'Matching Rule executed!');
        }

        // === Forward Chaining
        if ($actionType === 'Forward Chaining') {
            return redirect()
                ->route('inference.fc', ['user_id' => $user_id, 'case_num' => $user_id])
                ->with('success', 'Forward Chaining executed!');
        }

        // === Backward Chaining
        if ($actionType === 'Backward Chaining') {
            return redirect()
                ->route('inference.bc', ['user_id' => $user_id, 'case_num' => $user_id])
                ->with('success', 'Backward Chaining executed!');
        }

        // === Support Vector Machine (train → infer → insert ke inferensi_user_{userId} + diagnostics)
        if ($actionType === 'Support Vector Machine') {
            $kernel = $request->input('svm_kernel', 'sgd'); // contoh: sgd | rbf:D=1024:gamma=0.25 | sigmoid:D=...
            $kernelShort = strtolower(explode(':', $kernel)[0]);

            $phpBin = $this->resolvePhpBinary();

            // DIAGNOSTICS
            $diag = [];
            $diag[] = $this->diagLine('User ID', (string)$user_id);
            $diag[] = $this->diagLine('Kernel', $kernel);
            $diag[] = $this->diagLine('Kernel short', $kernelShort);
            $diag[] = $this->diagLine('PHP BIN', $phpBin, is_file($phpBin) || Str::startsWith($phpBin, 'php'));

            // Locate SVM.php
            try {
                $svmScript = $this->resolveScriptPath(env('SVM_SCRIPT'), [
                    'scripts/decision-tree/SVM.php',
                    'app/SVM.php', 'app/Console/SVM.php', 'SVM.php',
                ]);
                $diag[] = $this->diagLine('SVM.php', $svmScript, true);
            } catch (\Throwable $e) {
                $diag[] = $this->diagLine('SVM.php', 'NOT FOUND', false);
                return back()->with('error', "SVM: {$e->getMessage()}")->with('svm_diag', implode("\n", $diag));
            }

            // Count before (inferensi)
            $before = $this->countInferensi($user_id);
            $diag[] = $this->diagLine('Inferensi before', (string)$before);

            // TRAIN
            $trainCmd = [$phpBin, $svmScript, (string)$user_id, (string)$user_id, $kernel, "--table={$table}"];
            $trainRes = $this->runProcess($trainCmd, 600);
            $diag[]   = $this->diagLine('Train CMD', $trainRes['cmd'], $trainRes['ok']);

            // Cek model JSON hasil training (perkiraan path)
            $modelPath   = $this->modelPathGuess($user_id, $kernel);
            $modelExists = is_file($modelPath);
            $diag[]      = $this->diagLine('Model JSON', $modelExists ? $modelPath : 'NOT FOUND', $modelExists);

            if (!$trainRes['ok']) {
                $msg = "Training SVM gagal.\n\nCMD:\n{$trainRes['cmd']}\n\nSTDERR:\n{$trainRes['stderr']}\n\nSTDOUT:\n{$trainRes['stdout']}";
                return back()->with('error', $msg)->with('svm_diag', implode("\n", $diag));
            }

            // Locate SVMInfer.php
            try {
                $inferScript = $this->resolveScriptPath(env('SVM_INFER_SCRIPT'), [
                    'scripts/decision-tree/SVMInfer.php',
                    'app/SVMInfer.php', 'app/Console/SVMInfer.php', 'SVMInfer.php',
                ]);
                $diag[] = $this->diagLine('SVMInfer.php', $inferScript, true);
            } catch (\Throwable $e) {
                $diag[] = $this->diagLine('SVMInfer.php', 'NOT FOUND', false);
                $okMsg  = "Training SVM OK.\n{$trainRes['stdout']}\n\n(Perhatian: runner SVMInfer.php tidak ditemukan, jadi belum insert ke inferensi)";
                return back()->with('success', $okMsg)->with('svm_diag', implode("\n", $diag));
            }

            // INFER
            $inferCmd = [
                $phpBin,
                $inferScript,
                (string) $user_id,
                (string) $user_id,
                "--table={$table}",
                "--kernel={$kernelShort}",
            ];
            $inferRes = $this->runProcess($inferCmd, 300);
            $diag[]   = $this->diagLine('Infer CMD', $inferRes['cmd'], $inferRes['ok']);

            // AFTER
            $after = $this->countInferensi($user_id);
            $added = $after - $before;
            $diag[] = $this->diagLine('Inferensi after', (string)$after, $after >= $before);
            $diag[] = $this->diagLine('Rows added', (string)$added, $added >= 0);

            if (!$inferRes['ok']) {
                $msg = "SVM Inference gagal.\n\nCMD:\n{$inferRes['cmd']}\n\nSTDERR:\n{$inferRes['stderr']}\n\nSTDOUT:\n{$inferRes['stdout']}";
                return back()->with('error', $msg)->with('svm_diag', implode("\n", $diag));
            }

            $ok = "Training SVM OK.\n{$trainRes['stdout']}\n\nInference OK (added {$added}).\n{$inferRes['stdout']}";
            return back()->with('success', $ok)->with('svm_diag', implode("\n", $diag));
        }

        return back()->with('error', 'Action tidak dikenali.');
    }

    /**
     * Edit 1 baris test case.
     */
    public function edit($case_id)
    {
        $user_id   = Auth::user()->user_id;
        $tableName = 'test_case_user_' . $user_id;

        $case = DB::table($tableName)->where('case_id', $case_id)->first();
        if (!$case) {
            return redirect()->route('test.case.form')->withErrors('Case not found.');
        }

        $atributs = DB::table('atribut')
            ->where('user_id', $user_id)
            ->where(function ($q) {
                $q->where('goal', 'F')->orWhere('goal', 0)->orWhereNull('goal');
            })
            ->orderBy('goal', 'desc')
            ->get();

        return view('admin.menu.testCaseEdit', compact('case', 'atributs', 'tableName'));
    }

    /**
     * Update 1 baris test case.
     */
    public function update(Request $request, $case_id)
    {
        $user_id   = Auth::user()->user_id;
        $tableName = 'test_case_user_' . $user_id;

        $atributs = DB::table('atribut')
            ->where('user_id', $user_id)
            ->orderBy('goal', 'desc')
            ->get();

        $data = [];
        foreach ($atributs as $atribut) {
            $kolom = "{$atribut->atribut_id}_{$atribut->atribut_name}";
            if ($request->has($kolom)) {
                $input = $request->input($kolom);

                $valid = DB::table('atribut_value')
                    ->where('user_id', $user_id)
                    ->where('atribut_id', $atribut->atribut_id)
                    ->where(DB::raw("CONCAT(value_id, '_', value_name)"), $input)
                    ->exists();

                if (!$valid) {
                    return back()->withErrors("Invalid value untuk atribut {$atribut->atribut_name}.");
                }
                $data[$kolom] = $input;
            }
        }

        if ($data) {
            DB::table($tableName)->where('case_id', $case_id)->update($data);
        }

        return redirect()->route('test.case.form')->with('success', 'Case updated successfully.');
    }

    /**
     * Hapus 1 baris test case.
     */
    public function destroy($case_id)
    {
        $user_id   = Auth::user()->user_id;
        $tableName = 'test_case_user_' . $user_id;

        DB::table($tableName)->where('case_id', $case_id)->delete();

        return redirect()->route('test.case.form')->with('success', 'Case deleted successfully.');
    }

    /* ============================ HELPERS: PATHS ============================ */

    /**
     * Resolve path skrip CLI dari ENV (opsional) + fallback kandidat relatif.
     * Terima absolut/relatif. Lempar exception kalau tak ketemu.
     */
    private function resolveScriptPath(?string $envPath, array $fallbackRelativeCandidates)
    {
        $candidates = [];

        if ($envPath) {
            $norm = str_replace('\\', '/', trim($envPath));
            $candidates[] = $this->isAbsolutePath($norm) ? $norm : base_path($norm);
        }

        foreach ($fallbackRelativeCandidates as $rel) {
            $candidates[] = base_path($rel);
        }

        foreach ($candidates as $c) {
            $rp = @realpath($c);
            if (($rp && is_file($rp)) || is_file($c)) {
                return $rp ?: $c;
            }
        }

        throw new \RuntimeException(
            "Script tidak ditemukan. Set .env (SVM_SCRIPT / SVM_INFER_SCRIPT) atau letakkan file di salah satu lokasi berikut:\n- " .
            implode("\n- ", $candidates)
        );
    }

    /**
     * Cek absolut path (Windows/Unix/UNC).
     */
    private function isAbsolutePath(string $p): bool
    {
        $p = str_replace('\\', '/', $p);
        return Str::startsWith($p, ['/'])                 // unix absolute
            || (bool) preg_match('#^[A-Za-z]:/#', $p)     // windows drive
            || Str::startsWith($p, ['//', '\\\\']);       // UNC
    }

    /* ============================ HELPERS: DEBUG ============================ */

    private function runProcess(array $cmd, int $timeoutSec = 600): array
    {
        $pretty = implode(' ', array_map(fn($p) => str_contains($p, ' ') ? "\"$p\"" : $p, $cmd));
        $proc = new Process($cmd, base_path(), null, null, $timeoutSec);
        $proc->run();
        return [
            'cmd'     => $pretty,
            'ok'      => $proc->isSuccessful(),
            'code'    => $proc->getExitCode(),
            'stdout'  => trim($proc->getOutput() ?? ''),
            'stderr'  => trim($proc->getErrorOutput() ?? ''),
        ];
    }

    private function countInferensi(int $userId): int
    {
        $t = "inferensi_user_{$userId}";
        return Schema::hasTable($t) ? (int) DB::table($t)->count() : 0;
    }

    private function modelPathGuess(int $userId, string $kernel): string
    {
        $storageDir = function_exists('storage_path') ? storage_path('app/svm') : base_path('svm_models');
        $short = strtolower(explode(':', $kernel)[0]);
        return rtrim($storageDir, '/\\')."/svm_user_{$userId}_{$short}.json";
    }

    private function resolvePhpBinary(): string
    {
        $phpBin = env('PHP_BIN');
        if (!$phpBin) {
            $finder = new PhpExecutableFinder();
            $phpBin = $finder->find(false) ?: 'php';
        }
        $lower = strtolower($phpBin);
        if (Str::endsWith($lower, 'php-cgi.exe')) {
            $cli = str_replace('php-cgi.exe', 'php.exe', $phpBin);
            if (is_file($cli)) $phpBin = $cli;
        }
        return $phpBin;
    }

    private function diagLine(string $key, string $value, bool $ok = true): string
    {
        $mark = $ok ? '✅' : '❌';
        return "{$mark} {$key}: {$value}";
    }
}

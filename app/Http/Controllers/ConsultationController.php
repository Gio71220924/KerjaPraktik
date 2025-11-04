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
            'case_num'  => $user_id,                 // kamu memang menyamakan case_num=user_id
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

        // === Support Vector Machine (train → infer → insert ke inferensi_user_{userId})
        if ($actionType === 'Support Vector Machine') {
            $kernel = $request->input('svm_kernel', 'sgd'); // contoh: sgd | rbf:D=1024:gamma=0.25 | sigmoid:D=...

            // 1) Cari PHP CLI
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

            // 2) Resolve path trainer SVM.php
            try {
                $svmScript = $this->resolveScriptPath(env('SVM_SCRIPT'), [
                    'scripts/decision-tree/SVM.php',
                    'app/SVM.php',
                    'app/Console/SVM.php',
                    'SVM.php',
                ]);
            } catch (\Throwable $e) {
                return back()->with('error', "SVM: " . $e->getMessage());
            }

            // 3) TRAIN: php SVM.php <uid> <uid> <kernel> --table=test_case_user_{uid}
            $trainCmd  = [$phpBin, $svmScript, (string)$user_id, (string)$user_id, $kernel, "--table={$table}"];
            $trainProc = new Process($trainCmd, base_path(), null, null, 600);
            $trainProc->run();
            $stdoutT = $trainProc->getOutput();
            $stderrT = $trainProc->getErrorOutput();

            if (!$trainProc->isSuccessful()) {
                $cmdStr = implode(' ', array_map(fn($p)=>str_contains($p,' ') ? "\"$p\"" : $p, $trainCmd));
                return back()->with('error',
                    "Training SVM gagal.\nCommand : {$cmdStr}\n\nStderr:\n{$stderrT}\n\nOutput:\n{$stdoutT}"
                );
            }

            // BEFORE count buat info
            $infTbl = "inferensi_user_{$user_id}";
            $before = Schema::hasTable($infTbl) ? (int) DB::table($infTbl)->count() : 0;

            // 4) Resolve path runner SVMInfer.php
            try {
                $inferScript = $this->resolveScriptPath(env('SVM_INFER_SCRIPT'), [
                    'scripts/decision-tree/SVMInfer.php',
                    'app/SVMInfer.php',
                    'app/Console/SVMInfer.php',
                    'SVMInfer.php',
                ]);
            } catch (\Throwable $e) {
                return back()->with('success', "Training SVM OK.\n{$stdoutT}\n\n(Perhatian: runner SVMInfer.php tidak ditemukan, jadi belum insert ke inferensi)");
            }

            // 5) INFER: php SVMInfer.php <uid> <uid> --table=test_case_user_{uid}
            $inferCmd  = [$phpBin, $inferScript, (string)$user_id, (string)$user_id, "--table={$table}"];
            $inferProc = new Process($inferCmd, base_path(), null, null, 300);
            $inferProc->run();
            $stdoutI = $inferProc->getOutput();
            $stderrI = $inferProc->getErrorOutput();

            if (!$inferProc->isSuccessful()) {
                $cmdStr = implode(' ', array_map(fn($p)=>str_contains($p,' ') ? "\"$p\"" : $p, $inferCmd));
                return back()->with('error',
                    "SVM Inference gagal.\nCommand : {$cmdStr}\n\nStderr:\n{$stderrI}\n\nOutput:\n{$stdoutI}"
                );
            }

            // AFTER count
            $after = Schema::hasTable($infTbl) ? (int) DB::table($infTbl)->count() : 0;
            $added = $after - $before;

            return back()->with('success', "Training SVM OK.\n{$stdoutT}\n\nInference OK (added {$added}).\n{$stdoutI}");
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

    /* ============================ Helpers ============================ */

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
}

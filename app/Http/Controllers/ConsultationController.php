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
    public function showConsultationForm()
    {
        $user = Auth::user();
        $generate = new \App\Models\Consultation();
        $generate->setTableForUser($user->user_id);

        $tableExists = $generate->tableExists();
        $generateCase = $tableExists ? $generate->getRules() : collect();

        $columns = $tableExists ? Schema::getColumnListing($generate->getTable()) : [];

        $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->paginate(25);
        
        return view('admin.menu.testCase', compact('generateCase', 'columns', 'tableExists'));
    }

    public function create()
    {
        $user_id = Auth::user()->user_id;
        $atributs = DB::table('atribut')
                ->where('user_id', $user_id)
                ->orderBy('goal', 'desc')
                ->where('goal', 'F') 
                ->get();
        return view('admin.menu.testCaseTambah', compact('atributs'));
    }

    public function store(Request $request)
    {
        $user_id  = Auth::id();
        $table    = 'test_case_user_' . $user_id;  // sumber data training default (dan tempat insert case)
        $atributs = DB::table('atribut')
                        ->where('user_id', $user_id)
                        ->where('goal', 'F')
                        ->orderBy('goal', 'desc')
                        ->get();

        // Siapkan payload insert
        $data = [
            'user_id'   => $user_id,
            'case_num'  => $user_id,                      // kamu biasa pakai user_id sebagai case_num
            'algoritma' => $request->input('action_type')
        ];

        foreach ($atributs as $atribut) {
            $kolom_name = "{$atribut->atribut_id}_{$atribut->atribut_name}";
            if ($request->has($kolom_name)) {
                $input_value = $request->input($kolom_name);

                $valid = DB::table('atribut_value')
                            ->where('user_id', $user_id)
                            ->where('atribut_id', $atribut->atribut_id)
                            ->where(DB::raw("CONCAT(value_id, '_', value_name)"), $input_value)
                            ->exists();
                if (!$valid) {
                    return back()->withErrors("Invalid value for attribute {$atribut->atribut_name}.");
                }
                $data[$kolom_name] = $input_value;
            }
        }

        if (empty($data)) {
            return back()->withErrors('No data to insert.');
        }

        // Insert consultation case
        DB::table($table)->insert($data);

        // Tentukan action
        $actionType = $request->input('action_type');

        if ($actionType === 'Matching Rule') {
            return redirect()->route('inference.generate', ['user_id' => $user_id, 'case_num' => $user_id])
                ->with('success', 'Matching Rule executed!');
        } elseif ($actionType === 'Forward Chaining') {
            return redirect()->route('inference.fc', ['user_id' => $user_id, 'case_num' => $user_id])
                ->with('success', 'Forward Chaining executed!');
        } elseif ($actionType === 'Backward Chaining') {
            return redirect()->route('inference.bc', ['user_id' => $user_id, 'case_num' => $user_id])
                ->with('success', 'Backward Chaining executed!');
        } elseif ($actionType === 'Support Vector Machine') {
            // === SVM: jalankan scripts/decision-tree/SVM.php ===
            $kernel = $request->input('svm_kernel', 'sgd');  // sgd | rbf:D=1024:gamma=0.25 | sigmoid:D=...

            // 1) Temukan PHP CLI (auto, hindari php-cgi.exe)
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

            // 2) Resolve path SVM.php dari .env SVM_SCRIPT + fallback
            try {
                $script = $this->resolveScriptPath(env('SVM_SCRIPT'));
            } catch (\Throwable $e) {
                return back()->with('error', "SVM: " . $e->getMessage());
            }

            // 3) Compose command: php SVM.php <user_id> <case_num> <kernel> --table=test_case_user_{user_id}
            $cmd = [
                $phpBin,
                $script,
                (string)$user_id,
                (string)$user_id,   // case_num = user_id
                $kernel,
                "--table={$table}"
            ];

            $proc = new Process($cmd, base_path(), null, null, 600);
            try {
                $proc->run();
                $stdout = $proc->getOutput();
                $stderr = $proc->getErrorOutput();

                if (!$proc->isSuccessful()) {
                    $cmdStr = implode(' ', array_map(
                        fn ($p) => str_contains($p, ' ') ? "\"$p\"" : $p,
                        $cmd
                    ));
                    return back()->with('error',
                        "Training SVM gagal.\nCommand : {$cmdStr}\n\nStderr:\n{$stderr}\n\nOutput:\n{$stdout}"
                    );
                }

                return back()->with('success', "Training SVM OK.\n{$stdout}");
            } catch (\Throwable $e) {
                return back()->with('error', "SVM Exception: " . $e->getMessage());
            }
        }

        return back()->with('error', 'Invalid action!');
    }

    public function edit($case_id)
    {
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;
    
        $case = DB::table($tableName)->where('case_id', $case_id)->first();
        if (!$case) {
            return redirect()->route('test.case.form')->withErrors('Case not found.');
        }
    
        $atributs = DB::table('atribut')
                    ->where('user_id', $user_id)
                    ->orderBy('goal', 'desc')
                    ->where('goal', 'F') 
                    ->get();
    
        return view('admin.menu.testCaseEdit', compact('case', 'atributs', 'tableName'));
    }

    public function update(Request $request, $case_id)
    {
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;

        $atributs = DB::table('atribut')
                    ->where('user_id', $user_id)
                    ->orderBy('goal', 'desc')
                    ->get();

        $data = [];
        foreach ($atributs as $atribut) {
            $kolom_name = "{$atribut->atribut_id}_{$atribut->atribut_name}";
            if ($request->has($kolom_name)) {
                $input_value = $request->input($kolom_name);
                $valid = DB::table('atribut_value')
                          ->where('user_id', $user_id)
                          ->where('atribut_id', $atribut->atribut_id)
                          ->where(DB::raw("CONCAT(value_id, '_', value_name)"), $input_value)
                          ->exists();
                if (!$valid) {
                    return back()->withErrors("Invalid value for attribute {$atribut->atribut_name}.");
                }
                $data[$kolom_name] = $input_value;
            }
        }
        
        DB::table($tableName)->where('case_id', $case_id)->update($data);

        return redirect()->route('test.case.form')->with('success', 'Case updated successfully.');
    }

    public function destroy($case_id)
    {
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;
        DB::table($tableName)->where('case_id', $case_id)->delete();
        return redirect()->route('test.case.form')->with('success', 'Case deleted successfully.');
    }

    /* ============================ Helpers SVM ============================ */

    /**
     * Resolve path SVM.php dari .env SVM_SCRIPT (relatif/absolut) + fallback umum.
     * Lempar exception kalau tidak ketemu.
     */
    private function resolveScriptPath(?string $envPath): string
    {
        $candidates = [];

        if ($envPath) {
            $norm = str_replace('\\', '/', trim($envPath));
            if ($this->isAbsolutePath($norm)) {
                $candidates[] = $norm;               // absolut
            } else {
                $candidates[] = base_path($norm);    // relatif dari root proyek
            }
        }

        // fallback sesuai struktur kamu
        $candidates[] = base_path('scripts/decision-tree/SVM.php');
        $candidates[] = base_path('app/SVM.php');
        $candidates[] = base_path('app/Console/SVM.php');
        $candidates[] = base_path('SVM.php');

        foreach ($candidates as $c) {
            $rp = @realpath($c);
            if (($rp && is_file($rp)) || is_file($c)) {
                return $rp ?: $c;
            }
        }

        throw new \RuntimeException(
            "Script SVM.php tidak ditemukan. Coba set .env SVM_SCRIPT ke lokasi file Anda.\nDiperiksa:\n- " . implode("\n- ", $candidates)
        );
    }

    /** Deteksi absolut path (Windows/Unix/UNC) */
    private function isAbsolutePath(string $p): bool
    {
        $p = str_replace('\\', '/', $p);
        return Str::startsWith($p, ['/'])                 // unix absolute
            || (bool) preg_match('#^[A-Za-z]:/#', $p)     // windows drive
            || Str::startsWith($p, ['//', '\\\\']);       // UNC
    }
}

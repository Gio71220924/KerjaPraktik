<?php

namespace App\Http\Controllers;

use App\Support\SvmModelLocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class SVMController extends Controller
{
    /** Halaman UI */
    public function show()
    {
        $userId = Auth::user()->user_id;

        // Ambil atribut non-goal
        $atributs = DB::table('atribut')
            ->where('user_id', $userId)
            ->where(function ($q) { $q->whereNull('goal')->orWhere('goal', 0)->orWhere('goal', 'F'); })
            ->orderBy('atribut_id')
            ->get();

        // Ambil goal (opsional)
        $goalAttr = DB::table('atribut')
            ->where('user_id', $userId)
            ->where(function($q){ $q->where('goal',1)->orWhere('goal','T'); })
            ->first();

        // Values per atribut
        $valuesByAttr = [];
        foreach ($atributs as $a) {
            $valuesByAttr[$a->atribut_id] = DB::table('atribut_value')
                ->where('user_id', $userId)
                ->where('atribut_id', $a->atribut_id)
                ->orderBy('value_id')
                ->get();
        }

        // Values goal (opsional)
        $goalValues = collect();
        if ($goalAttr) {
            $goalValues = DB::table('atribut_value')
                ->where('user_id', $userId)
                ->where('atribut_id', $goalAttr->atribut_id)
                ->orderBy('value_id')
                ->get();
        }

        // Riwayat SVM (hanya success/failed; sembunyikan 'case')
        $svmData = [];
        $table = "svm_user_{$userId}";
        try {
            $exists = DB::selectOne("
                SELECT COUNT(*) AS c
                FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = ?
                LIMIT 1
            ", [$table]);
            if ($exists && (int)$exists->c > 0) {
                $svmData = DB::table($table)
                    ->where('status', '!=', 'case')
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get();
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Distribusi kelas di case_user_{userId} (untuk UI)
        $classStats = collect();
        try {
            $caseTable = "case_user_{$userId}";
            if ($goalAttr && Schema::hasTable($caseTable)) {
                $goalCol = $goalAttr->atribut_id . '_' . $goalAttr->atribut_name;
                $classStats = DB::table($caseTable)
                    ->selectRaw("`{$goalCol}` as label, COUNT(*) as total")
                    ->whereNotNull($goalCol)
                    ->where($goalCol, '!=', '')
                    ->groupBy($goalCol)
                    ->orderByDesc('total')
                    ->get();
            }
        } catch (\Throwable $e) {
            $classStats = collect();
        }

        return view('admin.menu.SVM', compact('atributs','goalAttr','valuesByAttr','goalValues','svmData','classStats'))
            ->with(['message' => null]);
    }

    /** Train manual (sumber case_user_{userId}) */
    public function generateSVM(Request $request)
    {
        $userId = Auth::user()->user_id;

        $kernel = (string)$request->input('kernel', 'sgd');
        $table  = "case_user_{$userId}";

        // Ambil hasil train + meta (confusion matrix, samples, dll.)
        $train = $this->runTraining($userId, $userId, $kernel, false, $table);

        if ($train['error'] ?? false) {
            return back()->with('svm_err', "Training gagal:\n" . ($train['message'] ?? ''));
        }

        $meta = $train['json'] ?? [];

        return back()
            ->with('svm_ok', trim($train['stdout'] ?? 'Training selesai.'))
            ->with('svm_meta', $meta);
    }

    /**
     * Input atribut via form → train (tanpa log 'case') → predict
     * → INSERT riwayat ke test_case_user_{userId}
     * → INSERT hasil ke inferensi_user_{userId} (pakai case_id dari test_case_user)
     */
    public function storeCaseAndTrain(Request $request)
    {
        $userId = Auth::user()->user_id;

        $request->validate([
            'kernel' => 'required|string',
            'attr'   => 'required|array',
            'table'  => 'nullable|string',
        ]);

        // Ambil seluruh atribut
        $atributs = DB::table('atribut')
            ->where('user_id', $userId)
            ->orderBy('atribut_id')
            ->get();

        $goalAttr = $atributs->first(fn ($a) =>
            (string)($a->goal ?? '') === 'T' || (int)($a->goal ?? 0) === 1
        );
        if (!$goalAttr) {
            return back()->with('svm_err', 'Atribut goal belum ditentukan (goal=1/T).');
        }

        // Kumpulkan input atribut non-goal
        $inputAttr = [];
        foreach ($atributs as $a) {
            $isGoal = (string)($a->goal ?? '') === 'T' || (int)($a->goal ?? 0) === 1;
            if ($isGoal) continue;

            $col = $a->atribut_id . '_' . $a->atribut_name;
            $val = $request->input("attr.{$a->atribut_id}");
            if ($val !== null && $val !== '') {
                $inputAttr[$col] = $val;
            }
        }

        $goalCol   = $goalAttr->atribut_id . '_' . $goalAttr->atribut_name;
        $kernel    = (string)$request->input('kernel', 'sgd');
        $tableSrc  = "case_user_{$userId}"; // train dari case_user

        // TRAIN
        $train = $this->runTraining($userId, $userId, $kernel, false, $tableSrc);
        if ($train['error'] ?? false) {
            return back()->with('svm_err', "Training gagal:\n" . ($train['message'] ?? ''));
        }

        // PREDIKSI dari input form
        $pred = $this->predictFromForm($userId, $kernel, $inputAttr, $goalCol);
        // $pred: ['label','margin','goal_key','kernel','summary']

        // META eksekusi
        $execSec   = $train['json']['execution_time']['total_sec']   ?? null;
        $kernelOut = $train['json']['kernel']                        ?? $pred['kernel'];

        // === 1) SIMPAN RIWAYAT KE test_case_user_{userId} ===
        //     - pastikan tabel & kolom ada
        //     - insert satu baris, algoritma='Support Vector Machine'
        $caseId = $this->appendTestCaseRow(
            userId:   $userId,
            atribs:   $atributs,
            goalAttr: $goalAttr,
            input:    $inputAttr,
            goalVal:  $pred['label'],
            algo:     'Support Vector Machine'
        );

        // === 2) INSERT HASIL KE inferensi_user_{userId} (pakai case_id di atas) ===
        $this->insertInferenceAutoSchema(
            userId:     $userId,
            caseId:     (string)$caseId,              // <- sinkron dengan test_case_user
            goalKey:    $pred['goal_key'],
            goalLabel:  $pred['label'],
            margin:     $pred['margin'],
            execTime:   $execSec,
            kernel:     $kernelOut
        );

        $ok = "Training OK:\n" . ($train['stdout'] ?? '(no stdout)') . "\n\n"
            . "Prediksi:\n" . $pred['summary'];

        $meta = $train['json'] ?? [];
        $meta['predict'] = [
            'label'      => $pred['label'],
            'margin'     => $pred['margin'],
            'confidence' => $pred['confidence'] ?? null,
            'kernel'     => $pred['kernel'],
            'goal_key'   => $pred['goal_key'],
            'top'        => $pred['top'] ?? [],
        ];
        // sertakan confusion matrix hasil train supaya bisa divisualisasikan
        if (isset($train['json']['confusion'])) {
            $meta['confusion'] = $train['json']['confusion'];
        }

        return back()
            ->with('svm_ok', $ok)
            ->with('svm_meta', $meta);
    }

    /* ===========================================================
     * Test Case History Helpers
     * ===========================================================
     */

    /** Pastikan tabel test_case_user_{userId} ada; jika belum, buat. */
    private function ensureTestCaseTable(int $userId, $atributs, $goalAttr): void
    {
        $table = "test_case_user_{$userId}";

        if (!Schema::hasTable($table)) {
            // Buat tabel dasar
            DB::statement("
                CREATE TABLE `{$table}` (
                  `case_id` INT NOT NULL AUTO_INCREMENT,
                  `user_id` INT NOT NULL,
                  `case_num` INT NOT NULL,
                  `algoritma` VARCHAR(255) NULL,
                  PRIMARY KEY (`case_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        // Pastikan kolom atribut & goal ada (VARCHAR fleksibel)
        foreach ($atributs as $a) {
            $col = $a->atribut_id . '_' . $a->atribut_name;
            if (!Schema::hasColumn($table, $col)) {
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$col}` VARCHAR(255) NULL");
            }
        }
        // (opsional) jaga-jaga kolom meta
        foreach (['user_id','case_num','algoritma'] as $c) {
            if (!Schema::hasColumn($table, $c)) {
                $type = $c === 'algoritma' ? 'VARCHAR(255) NULL' : 'INT NOT NULL';
                DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$c}` {$type}");
            }
        }
    }

    /**
     * Insert satu baris ke test_case_user_{userId}
     * @return int case_id inserted
     */
    private function appendTestCaseRow(
        int $userId,
        $atribs,
        $goalAttr,
        array $input,
        string $goalVal,
        string $algo = 'Support Vector Machine'
    ): int {
        $table = "test_case_user_{$userId}";

        $this->ensureTestCaseTable($userId, $atribs, $goalAttr);

        // Payload: semua atribut non-goal dari input + kolom goal dengan hasil prediksi
        $payload = [
            'user_id'  => $userId,
            'case_num' => $userId,
            'algoritma'=> $algo,
        ];

        foreach ($atribs as $a) {
            $col = $a->atribut_id . '_' . $a->atribut_name;
            $isGoal = (string)($a->goal ?? '') === 'T' || (int)($a->goal ?? 0) === 1;
            if ($isGoal) {
                $payload[$col] = $goalVal;           // tulis label prediksi
            } else {
                // isi nilai input kalau ada, selain itu biarkan NULL
                $payload[$col] = $input[$col] ?? null;
            }
        }

        // Insert dan ambil case_id (AUTO_INCREMENT)
        // MySQL akan mengembalikan last insert id meski nama pk bukan 'id'
        $id = DB::table($table)->insertGetId($payload);

        return (int)$id;
    }

    /* ===========================================================
     * Inferensi (schema lama / fallback generik)
     * ===========================================================
     */

    private function insertInferenceAutoSchema(
        int $userId,
        string $caseId,
        string $goalKey,
        string $goalLabel,
        float $margin,
        ?float $execTime,
        string $kernel
    ): void {
        $table = "inferensi_user_{$userId}";

        if (!Schema::hasTable($table)) {
            DB::statement("
                CREATE TABLE `{$table}` (
                  `inf_id` int(11) NOT NULL AUTO_INCREMENT,
                  `case_id` varchar(100) NOT NULL,
                  `case_goal` varchar(200) NOT NULL,
                  `rule_id` varchar(100) NOT NULL,
                  `rule_goal` varchar(200) NOT NULL,
                  `match_value` decimal(5,4) NOT NULL,
                  `cocok` enum('1','0') NOT NULL,
                  `user_id` int(11) NOT NULL,
                  `waktu` decimal(16,14) NOT NULL DEFAULT 0.00000000000000,
                  PRIMARY KEY (`inf_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        }

        // cek kolom wajib
        $need = ['inf_id','case_id','case_goal','rule_id','rule_goal','match_value','cocok','user_id','waktu'];
        $legacy = true;
        foreach ($need as $c) if (!Schema::hasColumn($table, $c)) { $legacy = false; break; }

        if ($legacy) {
            $this->insertInferenceLegacy(
                userId:    $userId,
                table:     $table,
                caseId:    $caseId,
                caseGoal:  "{$goalKey} = {$goalLabel}",
                ruleId:    'SVM',
                ruleGoal:  "{$goalKey} = {$goalLabel} | kernel={$kernel}",
                margin:    $margin,
                execTime:  $execTime
            );
            return;
        }

        $this->insertInferenceGeneric(
            userId:    $userId,
            table:     $table,
            caseId:    $caseId,
            ruleId:    null,
            caseGoal:  "{$goalKey} = {$goalLabel}",
            ruleGoal:  "{$goalKey} = {$goalLabel} | kernel={$kernel}",
            match:     $margin,
            execTime:  $execTime,
            kernel:    $kernel
        );
    }

    private function insertInferenceLegacy(
        int $userId,
        string $table,
        string $caseId,
        string $caseGoal,
        string $ruleId,
        string $ruleGoal,
        float $margin,
        ?float $execTime
    ): void {
        $conf = 1.0 / (1.0 + exp(-abs($margin)));
        if ($conf < 0) $conf = 0;
        if ($conf > 1) $conf = 1;

        $matchValue = number_format($conf, 4, '.', '');
        $waktu      = number_format((float)($execTime ?? 0.0), 14, '.', '');

        DB::table($table)->insert([
            'case_id'     => $caseId,
            'case_goal'   => $caseGoal,
            'rule_id'     => $ruleId,                            // 'SVM'
            'rule_goal'   => $ruleGoal,                          // include kernel
            'match_value' => $matchValue,
            'cocok'       => '1',
            'user_id'     => $userId,
            'waktu'       => $waktu,
        ]);
    }

    private function insertInferenceGeneric(
        int $userId,
        string $table,
        string $caseId,
        ?int $ruleId,
        string $caseGoal,
        string $ruleGoal,
        float $match,
        ?float $execTime,
        ?string $kernel
    ): void {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$table}` (
              id INT AUTO_INCREMENT PRIMARY KEY,
              case_id INT NULL,
              rule_id INT NULL,
              user_id INT NULL,
              rule_goal LONGTEXT NULL,
              match_value DECIMAL(18,6) NULL,
              waktu DECIMAL(18,8) NULL,
              kernel VARCHAR(255) NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Tambah kolom yang mungkin belum ada pada tabel lama
        $this->ensureInferenceGenericColumns($table);

        // Legacy tables may have rule_id NOT NULL; avoid NULL insert
        $ruleIdVal = $ruleId ?? 0;

        DB::table($table)->insert([
            'case_id'     => (int)$caseId,
            'rule_id'     => $ruleIdVal,
            'user_id'     => $userId,
            'case_goal'   => $caseGoal,
            'rule_goal'   => $ruleGoal,
            'match_value' => $match,
            'waktu'       => $execTime,
            'kernel'      => $kernel,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /* ===========================================================
     * SVM: run & predict
     * ===========================================================
     */

    private function runTraining(
        int $userId,
        int $caseNum,
        string $kernel,
        bool $redirectBack = true,
        ?string $tableOverride = null
    ) {
        // Pastikan eksekusi web tidak dipotong 30s (nginx/apache) saat menunggu proses CLI
        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ignore_user_abort(true);

        $phpBin = env('PHP_BIN');
        if (!$phpBin) {
            $finder = new PhpExecutableFinder();
            $phpBin = $finder->find(false) ?: 'php';
        }
        $lower = strtolower($phpBin);
        if (str_ends_with($lower, 'php-cgi.exe')) {
            $cli = str_replace('php-cgi.exe', 'php.exe', $phpBin);
            if (is_file($cli)) $phpBin = $cli;
        }

        try {
            $script = $this->resolveScriptPath(env('SVM_SCRIPT'));
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            return $redirectBack
                ? redirect()->route('SVM.show')->with('svm_err', $msg)
                : ['error' => true, 'message' => $msg];
        }

        $memoryLimit = env('SVM_MEMORY_LIMIT', '1024M');
        $cmd = [$phpBin, '-d', 'max_execution_time=0'];
        if ($memoryLimit) {
            $cmd[] = '-d';
            $cmd[] = 'memory_limit=' . $memoryLimit;
        }
        $cmd[] = $script;
        $cmd[] = (string)$userId;
        $cmd[] = (string)$caseNum;
        $cmd[] = $kernel;
        if ($tableOverride) $cmd[] = "--table={$tableOverride}";

        $proc = new Process($cmd, base_path(), null, null, 600);

        try {
            $proc->run();
            $stdout = $proc->getOutput();
            $stderr = $proc->getErrorOutput();

            if (!$proc->isSuccessful()) {
                $cmdStr = implode(' ', array_map(fn ($p) => str_contains($p, ' ') ? "\"$p\"" : $p, $cmd));
                $msg = "Training gagal.\nCommand : {$cmdStr}\n\nStderr:\n{$stderr}\n\nOutput:\n{$stdout}";
                return $redirectBack
                    ? redirect()->route('SVM.show')->with('svm_err', $msg)
                    : ['error' => true, 'message' => $msg, 'stdout' => $stdout, 'stderr' => $stderr, 'cmd' => $cmdStr];
            }

            $json = $this->extractLastJson($stdout);

            return $redirectBack
                ? redirect()->route('SVM.show')->with('svm_ok', trim($stdout))
                : ['error'=>false, 'stdout'=>trim($stdout), 'json'=>$json];

        } catch (\Throwable $e) {
            $msg = "Exception saat training: " . $e->getMessage();
            return $redirectBack
                ? redirect()->route('SVM.show')->with('svm_err', $msg)
                : ['error' => true, 'message' => $msg];
        }
    }

    private function isAbsolutePath(string $p): bool
    {
        $p = str_replace('\\', '/', $p);
        return str_starts_with($p, '/')
            || (bool) preg_match('#^[A-Za-z]:/#', $p)
            || str_starts_with($p, '//') || str_starts_with($p, '\\\\');
    }

    private function resolveScriptPath(?string $envPath): string
    {
        $candidates = [];

        if ($envPath) {
            $norm = str_replace('\\', '/', trim($envPath));
            $candidates[] = $this->isAbsolutePath($norm) ? $norm : base_path($norm);
        }

        $candidates[] = base_path('app/SVM.php');
        $candidates[] = base_path('app/Console/SVM.php');
        $candidates[] = base_path('SVM.php');
        $candidates[] = base_path('scripts/decision-tree/SVM.php');

        foreach ($candidates as $c) {
            $rp = @realpath($c);
            if (($rp && is_file($rp)) || is_file($c)) {
                return $rp ?: $c;
            }
        }

        throw new \RuntimeException(
            "Script SVM.php tidak ditemukan.\nDiperiksa:\n- " . implode("\n- ", $candidates)
        );
    }

    private function extractLastJson(string $stdout): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $stdout))));
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $ln = $lines[$i];
            if ($ln !== '' && $ln[0] === '{') {
                $j = json_decode($ln, true);
                if (is_array($j)) return $j;
            }
        }
        return [];
    }

    /** Prediksi dari model JSON */
    private function predictFromForm(int $userId, string $kernel, array $inputAttr, string $goalCol): array
    {
        $kernelShort = explode(':', $kernel)[0];
        $fileName = "svm_user_{$userId}_{$kernelShort}.json";
        $modelPath = SvmModelLocator::locate($fileName);

        if ($modelPath === null) {
            $checked = array_map(
                fn($dir) => rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $fileName,
                SvmModelLocator::directories()
            );
            return [
                'label'   => 'UNKNOWN',
                'margin'  => 0.0,
                'goal_key'=> $goalCol,
                'kernel'  => $kernelShort,
                'summary' => "Model tidak ditemukan: " . implode(', ', $checked)
            ];
        }

        $model = json_decode(@file_get_contents($modelPath), true);
        if (!$model || !isset($model['weights'])) {
            return [
                'label'   => 'UNKNOWN',
                'margin'  => 0.0,
                'goal_key'=> $goalCol,
                'kernel'  => $kernelShort,
                'summary' => "Model JSON tidak valid."
            ];
        }

        $goalKey     = $model['goal_column'] ?? $goalCol;
        $baseIndex   = $model['feature_index'] ?? [];
        $numMinmax   = $model['numeric_minmax'] ?? [];
        $labelMap    = $model['label_map'] ?? ['+1'=>'POS','-1'=>'NEG'];
        $classes     = $model['classes'] ?? null;             // multi-class (jika ada)
        $numClasses  = is_array($classes) ? count($classes) : null;
        $kernelType  = $model['kernel'] ?? $kernelShort;
        $kernelMeta  = $model['kernel_meta'] ?? [];
        $W           = $model['weights'];

        // vektor dasar
        $B = count($baseIndex);
        $xBase = array_fill(0, $B, 0.0);

        // numeric
        foreach ($numMinmax as $col => $mm) {
            if (!array_key_exists($col, $inputAttr)) continue;
            $min = (float)$mm['min']; $max = (float)$mm['max'];
            $v   = (float)$inputAttr[$col];
            $z   = ($max > $min) ? ($v - $min) / ($max - $min) : 0.0;
            $idx = $baseIndex["NUM::{$col}"] ?? null;
            if ($idx !== null) $xBase[$idx] = $z;
        }

        // kategorikal (one-hot)
        foreach ($baseIndex as $key => $idx) {
            if (str_starts_with($key, 'CAT::')) {
                $parts = explode('::', $key, 3);
                if (count($parts) !== 3) continue;
                [, $col, $val] = $parts;
                if (array_key_exists($col, $inputAttr) && (string)$inputAttr[$col] === $val) {
                    $xBase[$idx] = 1.0;
                }
            }
        }

        // kernel map + bias
        $z = $this->applyKernel($xBase, $kernelType, $kernelMeta, $baseIndex, $numMinmax);
        $z[] = 1.0;

        // Hitung skor & confidence untuk semua kelas
        $classScores = [];
        if (is_array($classes) && isset($W[0]) && is_array($W[0])) {
            $L = count($z);
            // Ambil skor linear
            $logits = [];
            foreach ($classes as $idx => $lbl) {
                $s = 0.0;
                for ($k=0; $k<$L; $k++) {
                    $s += ($W[$idx][$k] ?? 0.0) * $z[$k];
                }
                $logits[$idx] = $s;
            }
            // Softmax untuk menghasilkan probabilitas yang ter-normalisasi
            $maxLogit = max($logits);
            $expSum = 0.0;
            foreach ($logits as $v) {
                $expSum += exp($v - $maxLogit);
            }
            foreach ($classes as $idx => $lbl) {
                $prob = $expSum > 0 ? exp($logits[$idx] - $maxLogit) / $expSum : 0.0;
                $classScores[] = [
                    'label'      => $lbl,
                    'margin'     => (float)$logits[$idx],
                    'confidence' => max(0.0, min(1.0, $prob)),
                ];
            }
        } else {
            // Backward-compat: model binary lama
            $s = 0.0;
            for ($k=0; $k<count($z); $k++) {
                $s += ($W[$k] ?? 0.0) * $z[$k];
            }
            $pPos = 1.0 / (1.0 + exp(-$s)); // prob kelas +1
            $pNeg = 1.0 - $pPos;            // prob kelas -1
            $posLbl = $labelMap['+1'] ?? '+1';
            $negLbl = $labelMap['-1'] ?? '-1';
            $classScores[] = [
                'label'      => $posLbl,
                'margin'     => (float)$s,
                'confidence' => max(0.0, min(1.0, $pPos)),
            ];
            $classScores[] = [
                'label'      => $negLbl,
                'margin'     => (float)$s,
                'confidence' => max(0.0, min(1.0, $pNeg)),
            ];
        }

        // Urutkan berdasarkan confidence (descending)
        usort($classScores, function(array $a, array $b): int {
            return ($b['confidence'] <=> $a['confidence']);
        });

        // Ambil prediksi utama dari kelas dengan confidence tertinggi
        $predLbl = 'UNKNOWN';
        $dot     = 0.0;
        $conf    = 0.0;
        if (!empty($classScores)) {
            $best   = $classScores[0];
            $predLbl = $best['label'];
            $dot     = $best['margin'];
            $conf    = $best['confidence'];
        }

        // Top-k (mis. 3) label teratas dengan confidence
        $top = array_slice($classScores, 0, 3);

        return [
            'label'      => $predLbl,
            'margin'     => (float)$dot,
            'goal_key'   => $goalKey,
            'kernel'     => $kernelType,
            'confidence' => $conf,
            'top'        => $top,
            'summary'    => "Predict : {$predLbl} (margin=".number_format($dot,4).")\n".
                            "Confidence (est.) : ".number_format($conf*100,2)."%\n".
                            "GoalCol : {$goalKey}\n".
                            "Kernel  : {$kernelType}"
        ];
    }

    private function applyKernel(array $xBase, string $type, array $meta, array $baseIndex, array $numMinmax): array
    {
        if ($type === 'sgd') return $xBase;

        if ($type === 'rbf') {
            $D = (int)($meta['D'] ?? 128);
            $gamma = (float)($meta['gamma'] ?? 0.25);
            $seed  = (int)($meta['seed'] ?? crc32(json_encode(array_keys($baseIndex))));
            mt_srand($seed);

            $B = count($xBase);
            $omega=[]; $b=[];
            for($j=0;$j<$D;$j++){
                $row=[];
                for($k=0;$k<$B;$k++){
                    $u1=max(mt_rand()/mt_getrandmax(),1e-12);
                    $u2=mt_rand()/mt_getrandmax();
                    $z=sqrt(-2.0*log($u1))*cos(2.0*M_PI*$u2);
                    $row[]=sqrt(2.0*$gamma)*$z;
                }
                $omega[]=$row;
            }
            for($j=0;$j<$D;$j++) $b[]=(mt_rand()/mt_getrandmax())*2.0*M_PI;
            $scale = sqrt(2.0/$D);

            $z = array_fill(0,$D,0.0);
            for($j=0;$j<$D;$j++){
                $dot=0.0;
                for($k=0;$k<$B;$k++) $dot += $omega[$j][$k]*$xBase[$k];
                $z[$j] = $scale * cos($dot + $b[$j]);
            }
            return $z;
        }

        if ($type === 'sigmoid') {
            $D=(int)($meta['D'] ?? 128);
            $scale=(float)($meta['scale'] ?? 1.0);
            $coef0=(float)($meta['coef0'] ?? 0.0);
            $seed= (int)($meta['seed'] ?? (14641 ^ crc32(json_encode(array_keys($baseIndex)))));
            mt_srand($seed);

            $B=count($xBase);
            $W=[]; $b=[];
            for($j=0;$j<$D;$j++){
                $row=[];
                for($k=0;$k<$B;$k++){
                    $u1=max(mt_rand()/mt_getrandmax(),1e-12);
                    $u2=mt_rand()/mt_getrandmax();
                    $z=sqrt(-2.0*log($u1))*cos(2.0*M_PI*$u2);
                    $row[]=$scale*$z;
                }
                $W[]=$row; $b[]=$coef0;
            }
            $norm=sqrt(1.0/$D);
            $z=array_fill(0,$D,0.0);
            for($j=0;$j<$D;$j++){
                $dot=0.0;
                for($k=0;$k<$B;$k++) $dot+=$W[$j][$k]*$xBase[$k];
                $z[$j]=$norm*tanh($dot+$b[$j]);
            }
            return $z;
        }

        return $xBase;
    }

    /**
     * Pastikan tabel inferensi generik punya kolom minimal yang dibutuhkan agar insert tidak gagal.
     */
    private function ensureInferenceGenericColumns(string $table): void
    {
        $cols = [
            'case_id'     => 'INT NULL',
            'rule_id'     => 'INT NULL',
            'user_id'     => 'INT NULL',
            'case_goal'   => 'LONGTEXT NULL',
            'rule_goal'   => 'LONGTEXT NULL',
            'match_value' => 'DECIMAL(18,6) NULL',
            'waktu'       => 'DECIMAL(18,8) NULL',
            'kernel'      => 'VARCHAR(255) NULL',
            'created_at'  => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at'  => 'TIMESTAMP NULL DEFAULT NULL',
        ];

        foreach ($cols as $col => $ddl) {
            try {
                if (!Schema::hasColumn($table, $col)) {
                    DB::statement("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$ddl}");
                } elseif ($col === 'user_id') {
                    // Pastikan user_id nullable agar tabel lama yang NOT NULL tidak memblok insert
                    DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `user_id` INT NULL");
                }
            } catch (\Throwable $e) {
                // jika gagal (mis. hak akses), biarkan supaya error/log tampil jelas
            }
        }
    }
}

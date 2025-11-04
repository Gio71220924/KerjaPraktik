<?php

namespace App\Http\Controllers;

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

        // Ambil goal (boleh null)
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

        // Riwayat SVM (opsional)
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
                $svmData = DB::table($table)->orderByDesc('id')->limit(50)->get();
            }
        } catch (\Throwable $e) { /* ignore */ }

        return view('admin.menu.SVM', compact('atributs','goalAttr','valuesByAttr','goalValues','svmData'))
            ->with(['message' => null]);
    }

    /** Train manual */
    public function generateSVM(Request $request)
    {
        $data = $request->validate([
            'user_id'  => 'required|integer',
            'case_num' => 'required|integer',
            'kernel'   => 'nullable|string',
            'table'    => 'nullable|string',
        ]);

        $userId = (int)$data['user_id'];
        $case   = (int)$data['case_num'];      // ← perbaikan: was $caseId
        $kernel = $data['kernel'] ?: 'sgd';
        $table  = $data['table'] ?? null;

        return $this->runTraining($userId, $case, $kernel, redirectBack:true, tableOverride:$table);
    }

    /**
     * Input kasus via form → log ke svm_user_{id} → train → prediksi → INSERT ke inferensi_user_{id}
     * Form: kernel + attr[atribut_id] = valueId_valueName
     * Goal tidak diisi user; dipakai goal dari model.
     */
    public function storeCaseAndTrain(Request $request)
    {
        $userId = Auth::id();

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
        $table     = $request->input('table'); // contoh: rule_user_{id}

        // Pastikan log table ada
        $this->ensureSvmLogTable($userId);

        // Simpan log 'case'
        $caseId = DB::table("svm_user_{$userId}")->insertGetId([
            'status'      => 'case',
            'output'      => 'Input case (UI SVM)',
            'goal_value'  => null,
            'input_json'  => json_encode($inputAttr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Train
        $train = $this->runTraining($userId, $caseId, $kernel, redirectBack:false, tableOverride:$table);
        if ($train['error'] ?? false) {
            return back()->with('svm_err', "Training gagal:\n" . ($train['message'] ?? ''));
        }

        // Prediksi
        $pred = $this->predictFromForm($userId, $kernel, $inputAttr, $goalCol);
        // $pred: ['label','margin','goal_key','kernel','summary']

        // Meta eksekusi
        $execSec   = $train['json']['execution_time']['total_sec']   ?? null;
        $kernelOut = $train['json']['kernel']                        ?? $pred['kernel'];

        // === INSERT KE TABEL INFERENSI (SCHEMA LEGACY) ===
        $this->insertInferenceAutoSchema(
            userId:     $userId,
            caseId:     (string)$caseId,
            goalKey:    $pred['goal_key'],
            goalLabel:  $pred['label'],
            margin:     $pred['margin'],
            execTime:   $execSec,
            kernel:     $kernelOut
        );

        $ok = "Input case tersimpan (svm_user_{$userId}#{$caseId}).\n\n"
            . "Training OK:\n" . ($train['stdout'] ?? '(no stdout)') . "\n\n"
            . "Prediksi:\n" . $pred['summary'];

        return back()->with('svm_ok', $ok);
    }

    /** ====== Helpers untuk INSERT inferensi sesuai schema lama ====== */

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

        // Kalau tabel belum ada, buat sesuai schema lama (punya kamu)
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

        // Pastikan kolom-kolom wajib schema lama tersedia
        $needCols = ['inf_id','case_id','case_goal','rule_id','rule_goal','match_value','cocok','user_id','waktu'];
        $hasAll = true;
        foreach ($needCols as $c) {
            if (!Schema::hasColumn($table, $c)) { $hasAll = false; break; }
        }

        if ($hasAll) {
            // pakai schema lama (sesuai tabel kamu)
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

        // Fallback: kalau bukan schema lama, insert minimal (versi generik)
        $this->insertInferenceGeneric(
            userId:    $userId,
            table:     $table,
            caseId:    $caseId,
            ruleId:    null,
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
        // Konversi margin SVM → confidence 0..1 (pakai sigmoid(|margin|))
        $conf = 1.0 / (1.0 + exp(-abs($margin)));
        if ($conf < 0) $conf = 0;
        if ($conf > 1) $conf = 1;

        // Format sesuai tipe kolom
        $matchValue = number_format($conf, 4, '.', '');                 // DECIMAL(5,4)
        $waktu      = number_format((float)($execTime ?? 0.0), 14, '.', ''); // DECIMAL(16,14)

        DB::table($table)->insert([
            'case_id'     => $caseId,
            'case_goal'   => $caseGoal,
            'rule_id'     => $ruleId,              // 'SVM'
            'rule_goal'   => $ruleGoal,            // tampilkan kernel juga
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
        string $ruleGoal,
        float $match,
        ?float $execTime,
        ?string $kernel
    ): void {
        // Buat tabel generik kalau belum ada (versi sebelumnya)
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$table}` (
              id INT AUTO_INCREMENT PRIMARY KEY,
              case_id INT NULL,
              rule_id INT NULL,
              rule_goal LONGTEXT NULL,
              match_value DECIMAL(18,6) NULL,
              waktu DECIMAL(18,8) NULL,
              kernel VARCHAR(255) NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        DB::table($table)->insert([
            'case_id'     => (int)$caseId,
            'rule_id'     => $ruleId,
            'rule_goal'   => $ruleGoal,
            'match_value' => $match,
            'waktu'       => $execTime,
            'kernel'      => $kernel,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** ====== SVM run & prediksi ====== */

    private function ensureSvmLogTable(int $userId): void
    {
        $logTable = "svm_user_{$userId}";
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$logTable}` (
              id INT AUTO_INCREMENT PRIMARY KEY,
              status VARCHAR(50),
              execution_time DECIMAL(12,6) NULL,
              model_path VARCHAR(1024) NULL,
              output LONGTEXT NULL,
              goal_value VARCHAR(255) NULL,
              input_json LONGTEXT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        try { DB::statement("ALTER TABLE `{$logTable}` ADD COLUMN goal_value VARCHAR(255) NULL"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `{$logTable}` ADD COLUMN input_json LONGTEXT NULL"); } catch (\Throwable $e) {}
    }

    private function runTraining(
        int $userId,
        int $caseNum,
        string $kernel,
        bool $redirectBack = true,
        ?string $tableOverride = null
    ) {
        // cari php cli
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

        // lokasi skrip
        try {
            $script = $this->resolveScriptPath(env('SVM_SCRIPT'));
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            return $redirectBack
                ? redirect()->route('SVM.show')->with('svm_err', $msg)
                : ['error' => true, 'message' => $msg];
        }

        // command
        $cmd = [$phpBin, $script, (string)$userId, (string)$caseNum, $kernel];
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

            // JSON terakhir dari stdout
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
        return Str::startsWith($p, ['/'])
            || (bool) preg_match('#^[A-Za-z]:/#', $p)
            || Str::startsWith($p, ['//', '\\\\']);
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
        $storageDir = function_exists('storage_path') ? storage_path('app/svm') : base_path('svm_models');
        $kernelShort = explode(':', $kernel)[0];
        $modelPath  = rtrim($storageDir, '/\\') . "/svm_user_{$userId}_{$kernelShort}.json";
        if (!is_file($modelPath)) {
            return [
                'label'   => 'UNKNOWN',
                'margin'  => 0.0,
                'goal_key'=> $goalCol,
                'kernel'  => $kernelShort,
                'summary' => "Model tidak ditemukan: {$modelPath}"
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

        // kategorikal
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

        // kernel map + bias + dot
        $z = $this->applyKernel($xBase, $kernelType, $kernelMeta, $baseIndex, $numMinmax);
        $z[] = 1.0;

        $dot = 0.0;
        for ($k=0; $k<count($z); $k++) $dot += ($W[$k] ?? 0.0) * $z[$k];
        $predSign = ($dot >= 0) ? '+1' : '-1';
        $predLbl  = $labelMap[$predSign] ?? $predSign;

        return [
            'label'   => $predLbl,
            'margin'  => (float)$dot,
            'goal_key'=> $goalKey,
            'kernel'  => $kernelType,
            'summary' => "Predict : {$predLbl} (margin=".number_format($dot,4).")\n".
                         "GoalCol : {$goalKey}\n".
                         "Kernel  : {$kernelType}"
        ];
    }

    private function applyKernel(array $xBase, string $type, array $meta, array $baseIndex, array $numMinmax): array
    {
        if ($type === 'sgd') return $xBase;

        if ($type === 'rbf') {
            $D = (int)($meta['D'] ?? 1024);
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
            $D=(int)($meta['D'] ?? 1024);
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
}

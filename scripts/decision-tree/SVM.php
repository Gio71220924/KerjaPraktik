<?php
declare(strict_types=1);

/**
 * Linear SVM (hinge loss + L2) via SGD (Pegasos-style), pure PHP.
 * - Sumber data: case_user_{user_id} (fallback: test_case_user_{user_id})
 * - Goal kolom diambil dari tabel `atribut` (goal=1) => {atribut_id}_{atribut_name}
 * - Fitur: one-hot untuk kategorikal, numeric dipakai apa adanya, + intercept (bias)
 * - Model disimpan: storage/app/svm/linear_svm_user_{user_id}.json
 * - Log ringkas: tabel svm_user_{user_id}
 */

/////////////////////////// Helpers ///////////////////////////
$projectRoot = str_replace('\\', '/', dirname(__DIR__, 2));

function envv(string $key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}
function norm(string $s): string {
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/i', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}
function findGoalKey(array $columns, string $wanted): ?string {
    if (in_array($wanted, $columns, true)) return $wanted;
    $wantedN = norm($wanted);
    $map = [];
    foreach ($columns as $c) $map[norm($c)] = $c;
    return $map[$wantedN] ?? null;
}
function table_exists(mysqli $conn, string $db, string $table): bool {
    $db = $conn->real_escape_string($db);
    $table = $conn->real_escape_string($table);
    $q = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema='{$db}' AND table_name='{$table}' LIMIT 1");
    return $q && $q->num_rows > 0;
}

/////////////////////////// Arg parsing ///////////////////////////
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}
global $argv, $argc;
if ($argc < 3) {
    fwrite(STDERR, "Usage: php SVM.php <user_id> <case_num>\n");
    exit(1);
}
$userId  = (int) $argv[1];
$caseNum = (int) $argv[2];

/////////////////////////// DB connect ///////////////////////////
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host     = (string) envv('DB_HOST', '127.0.0.1');
$port     = (int)    envv('DB_PORT', 3307);
$database = (string) envv('DB_DATABASE', 'expertt');
$username = (string) envv('DB_USERNAME', 'root');
$password = (string) envv('DB_PASSWORD', '');

try {
    $db = new mysqli($host, $username, $password, $database, $port);
    $db->set_charset('utf8mb4');
} catch (Throwable $e) {
    fwrite(STDERR, "❌ DB connect failed ({$e->getCode()}): {$e->getMessage()}\n");
    exit(1);
}

/////////////////////////// Tentukan sumber & goal ///////////////////////////
$sourceTable = table_exists($db, $database, "case_user_{$userId}")
    ? "case_user_{$userId}"
    : (table_exists($db, $database, "test_case_user_{$userId}") ? "test_case_user_{$userId}" : null);

if ($sourceTable === null) {
    fwrite(STDERR, "❌ Tidak ditemukan tabel case_user_{$userId} maupun test_case_user_{$userId}.\n");
    exit(1);
}

$gq = $db->query("
    SELECT atribut_id, atribut_name
    FROM atribut
    WHERE user_id = {$userId} AND goal = 1
    LIMIT 1
");
if ($gq->num_rows === 0) {
    fwrite(STDERR, "❌ Atribut goal belum ditentukan untuk user ini.\n");
    exit(1);
}
$g = $gq->fetch_assoc();
$goalWanted = $g['atribut_id'] . '_' . $g['atribut_name'];

/////////////////////////// Ambil data ///////////////////////////
$res = $db->query("SELECT * FROM `{$sourceTable}`");
if ($res->num_rows === 0) {
    fwrite(STDERR, "❌ Tidak ada data pada tabel {$sourceTable}\n");
    exit(1);
}
$first = $res->fetch_assoc();
$columns = array_keys($first);
$goalKey = findGoalKey($columns, $goalWanted);
if ($goalKey === null) {
    fwrite(STDERR, "❌ Kolom goal '{$goalWanted}' tidak ditemukan. Kolom tersedia: ".implode(',', $columns)."\n");
    exit(1);
}
$res->data_seek(0);

/////////////////////////// Kumpulkan skema fitur ///////////////////////////
// Dua pass: (1) deteksi kategori & numeric, (2) bangun index fitur
$skipCols = ['case_id','user_id','case_num','algoritma',$goalKey];
$cats = [];       // cats[col][value] = true
$nums = [];       // nums[col] = ['min'=>..., 'max'=>...]
$labelsRaw = [];  // kumpulkan label

while ($row = $res->fetch_assoc()) {
    $label = $row[$goalKey] ?? null;
    if ($label === null || $label === '' || preg_match('/^(unknown|tidak diketahui)$/i', (string)$label)) {
        continue;
    }
    $labelsRaw[] = (string)$label;

    foreach ($row as $col => $val) {
        if (in_array($col, $skipCols, true)) continue;

        if (is_numeric($val)) {
            $v = (float)$val;
            if (!isset($nums[$col])) $nums[$col] = ['min'=>$v, 'max'=>$v];
            else {
                if ($v < $nums[$col]['min']) $nums[$col]['min'] = $v;
                if ($v > $nums[$col]['max']) $nums[$col]['max'] = $v;
            }
        } else {
            $s = trim((string)$val);
            if ($s === '') continue;
            if (!isset($cats[$col])) $cats[$col] = [];
            $cats[$col][$s] = true;
        }
    }
}
if (count($labelsRaw) === 0) {
    fwrite(STDERR, "❌ Semua label kosong/Unknown pada kolom {$goalKey}.\n");
    exit(1);
}

// Tentukan dua kelas (binary). Jika >2, ambil dua terbanyak.
$freq = array_count_values($labelsRaw);
arsort($freq);
$classes = array_keys($freq);
if (count($classes) < 2) {
    fwrite(STDERR, "❌ Butuh dua kelas untuk SVM. Ditemukan hanya: ".implode(',', $classes)."\n");
    exit(1);
}
$posLabel = $classes[0];
$negLabel = $classes[1];

// Bangun index fitur
$featIndex = [];  // map nama fitur → index
$idx = 0;
// numeric: 1 index per kolom
foreach ($nums as $col => $_) {
    $featIndex["NUM::$col"] = $idx++;
}
// categorical: one-hot per nilai
foreach ($cats as $col => $vals) {
    foreach (array_keys($vals) as $val) {
        $featIndex["CAT::$col::$val"] = $idx++;
    }
}
// intercept (bias)
$biasIndex = $idx++;
$dim = $idx;

/////////////////////////// Bangun X, y ///////////////////////////
$res->data_seek(0);
$X = []; // array<array<float>>
$y = []; // array<int> (+1/-1)
$totalRows = 0; $skipNoLabel = 0; $skipFeatZero = 0;

while ($row = $res->fetch_assoc()) {
    $totalRows++;
    $label = $row[$goalKey] ?? null;
    if ($label === null || $label === '' || preg_match('/^(unknown|tidak diketahui)$/i', (string)$label)) {
        $skipNoLabel++;
        continue;
    }
    $label = (string)$label;
    if ($label !== $posLabel && $label !== $negLabel) {
        // buang kelas lain; hanya biner
        continue;
    }
    $yi = ($label === $posLabel) ? +1 : -1;

    // vektor fitur dense
    $xi = array_fill(0, $dim, 0.0);

    foreach ($row as $col => $val) {
        if (in_array($col, $skipCols, true)) continue;

        if (isset($nums[$col])) {
            $min = $nums[$col]['min']; $max = $nums[$col]['max'];
            $v = (float)$val;
            $z = ($max > $min) ? ($v - $min) / ($max - $min) : 0.0; // min-max 0..1
            $xi[$featIndex["NUM::$col"]] = $z;
        } elseif (isset($cats[$col])) {
            $s = trim((string)$val);
            if ($s !== '' && isset($featIndex["CAT::$col::$s"])) {
                $xi[$featIndex["CAT::$col::$s"]] = 1.0;
            }
        }
    }

    // bias
    $xi[$biasIndex] = 1.0;

    // cek vektor non-kosong
    $sumAbs = 0.0;
    foreach ($xi as $vv) { $sumAbs += abs($vv); }
    if ($sumAbs == 0.0) {
        $skipFeatZero++;
        continue;
    }

    $X[] = $xi;
    $y[] = $yi;
}

if (count($X) === 0) {
    fwrite(STDERR, "❌ Dataset tidak memiliki sampel valid. total={$totalRows} kosong_label={$skipNoLabel} fitur_kosong={$skipFeatZero}\n");
    exit(1);
}

/////////////////////////// Train Linear SVM (SGD) ///////////////////////////
// Hinge loss + L2, Pegasos-like schedule
$lambda = 1e-4;        // regulasi
$epochs = 20;
$eta0   = 0.1;         // initial lr
$W = array_fill(0, $dim, 0.0);
$n = count($X);

$start = microtime(true);
$t = 0;
for ($ep = 0; $ep < $epochs; $ep++) {
    // shuffle
    $order = range(0, $n - 1);
    shuffle($order);

    foreach ($order as $i) {
        $t++;
        // learning rate decay
        $eta = $eta0 / (1.0 + $lambda * $eta0 * $t);

        // dot product
        $dot = 0.0;
        $xi = $X[$i]; $yi = $y[$i];
        $len = count($xi);
        for ($k = 0; $k < $len; $k++) $dot += $W[$k] * $xi[$k];

        // gradient step
        if ($yi * $dot < 1.0) {
            // w = w - eta*(lambda*w - yi*xi)
            for ($k = 0; $k < $len; $k++) {
                $W[$k] = $W[$k] - $eta * ($lambda * $W[$k] - $yi * $xi[$k]);
            }
        } else {
            // w = w - eta*(lambda*w)
            for ($k = 0; $k < $len; $k++) {
                $W[$k] = $W[$k] - $eta * ($lambda * $W[$k]);
            }
        }
        // optional: weight decay clamp
        // (biarkan apa adanya)
    }
}
$duration = microtime(true) - $start;

/////////////////////////// Metrics training sederhana ///////////////////////////
$correct = 0;
for ($i = 0; $i < $n; $i++) {
    $dot = 0.0;
    $xi = $X[$i]; $yi = $y[$i];
    $len = count($xi);
    for ($k = 0; $k < $len; $k++) $dot += $W[$k] * $xi[$k];
    $pred = ($dot >= 0) ? +1 : -1;
    if ($pred === $yi) $correct++;
}
$acc = $correct / $n;

/////////////////////////// Simpan model ///////////////////////////
$storageDir = $projectRoot . '/storage/app/svm';
if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
    fwrite(STDERR, "❌ Gagal membuat direktori penyimpanan model: {$storageDir}\n");
    exit(1);
}
$modelPath = $storageDir . "/linear_svm_user_{$userId}.json";

$model = [
    'type'     => 'linear_svm_sgd',
    'dim'      => $dim,
    'weights'  => $W,
    'bias_index' => $biasIndex,
    'lambda'   => $lambda,
    'epochs'   => $epochs,
    'eta0'     => $eta0,
    'goal_column' => $goalKey,
    'label_map' => ['+1' => $posLabel, '-1' => $negLabel],
    'feature_index' => $featIndex,
    'numeric_minmax' => $nums,
];

file_put_contents($modelPath, json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

/////////////////////////// Insert log ///////////////////////////
$logTable = "svm_user_{$userId}";
$db->query("
    CREATE TABLE IF NOT EXISTS `{$logTable}` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        status VARCHAR(50),
        execution_time DECIMAL(12,6) NULL,
        model_path VARCHAR(1024) NULL,
        output LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL
    )
");

$status = 'success';
$output = "Linear SVM (manual). source={$sourceTable}; goal={$goalKey}; classes={$posLabel}|{$negLabel}; "
        . "samples={$n}; acc_train=" . number_format($acc * 100, 2) . "%";

$stmt = $db->prepare("INSERT INTO `{$logTable}` (status, execution_time, model_path, output, created_at, updated_at)
                      VALUES (?, ?, ?, ?, NOW(), NOW())");
$stmt->bind_param('sdss', $status, $duration, $modelPath, $output);
$stmt->execute();

/////////////////////////// Output ///////////////////////////
echo "✅ Linear SVM (manual) terlatih.\n";
echo "📦 Model: {$modelPath}\n";
echo "⏱️ Waktu: " . number_format($duration, 6) . " detik\n";
echo "📊 Sampel: {$n}, Akurasi train: " . number_format($acc * 100, 2) . "%\n";
echo "🎯 Kelas: +1={$posLabel}, -1={$negLabel}\n";
echo json_encode([
    'status'         => 'success',
    'user_id'        => $userId,
    'source_table'   => $sourceTable,
    'goal_column'    => $goalKey,
    'classes'        => ['pos' => $posLabel, 'neg' => $negLabel],
    'model_path'     => $modelPath,
    'execution_time' => $duration,
    'samples'        => $n,
    'train_accuracy' => $acc,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$db->close();
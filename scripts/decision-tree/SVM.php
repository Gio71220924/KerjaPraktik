<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;
use Rubix\ML\Classifiers\SVC;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Kernels\SVM\RBF;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\Transformers\OneHotEncoder;

/**
 * Ambil environment variable yang andal untuk CGI/CLI.
 */
function envv(string $key, $default = null) {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

/**
 * Normalisasi nama kolom: huruf kecil, non-alfanumerik jadi '_', trim '_' berlebih.
 */
function norm(string $s): string {
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/i', '_', $s);
    $s = preg_replace('/_+/', '_', $s);
    return trim($s, '_');
}

/**
 * Cari key goal sebenarnya di daftar kolom dengan pendekatan normalisasi.
 * @param string[] $columns
 */
function findGoalKey(array $columns, string $wanted): ?string {
    // 1) match persis
    if (in_array($wanted, $columns, true)) {
        return $wanted;
    }
    // 2) match via normalisasi
    $wantedN = norm($wanted);
    $map = [];
    foreach ($columns as $c) {
        $map[norm($c)] = $c;
    }
    return $map[$wantedN] ?? null;
}

// ----------------- Arg parsing (CLI/CGI) -----------------
$sapi = PHP_SAPI; // 'cli' atau 'cgi-fcgi'
$userId = 0; $caseNum = 0;

if ($sapi === 'cli') {
    global $argv;
    if (!isset($argv[1], $argv[2])) {
        echo json_encode(['status'=>'error','message'=>'Parameter user_id dan case_num wajib diberikan.']), PHP_EOL;
        exit(1);
    }
    $userId  = (int) $argv[1];
    $caseNum = (int) $argv[2];
} elseif ($sapi === 'cgi-fcgi') {
    $userId  = (int) ($_GET['user_id']  ?? 0);
    $caseNum = (int) ($_GET['case_num'] ?? 0);
    if ($userId <= 0 || $caseNum <= 0) {
        echo json_encode(['status'=>'error','message'=>'Parameter user_id dan case_num wajib diberikan.']), PHP_EOL;
        exit(1);
    }
} else {
    echo json_encode(['status'=>'error','message'=>'Unsupported SAPI. Use CLI or CGI.']), PHP_EOL;
    exit(1);
}

$logs = [];
$connection = null;

try {
    // Load .env dari root Laravel
    $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
    $dotenv->safeLoad();

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host     = (string) envv('DB_HOST', '127.0.0.1');
    $port     = (int) envv('DB_PORT', 3307);  // penting: ambil dari .env (3307 pada setup-mu)
    $database = (string) envv('DB_DATABASE', 'expertt');
    $username = (string) envv('DB_USERNAME', 'root');
    $password = (string) envv('DB_PASSWORD', '');

    try {
        $connection = new mysqli($host, $username, $password, $database, $port);
        $connection->set_charset('utf8mb4');
    } catch (Throwable $e) {
        echo "❌ MySQL connect failed ({$e->getCode()}): {$e->getMessage()} ".
             "(host={$host} port={$port} user={$username})", PHP_EOL,
             json_encode(['status'=>'error','message'=>'db_connect_failed'], JSON_UNESCAPED_SLASHES), PHP_EOL;
        exit(1);
    }

    // Cek tabel dataset via information_schema
    $tableName = sprintf('test_case_user_%d', $userId);
    $escDb = $connection->real_escape_string($database);
    $escTb = $connection->real_escape_string($tableName);
    $check = $connection->query("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = '{$escDb}' AND table_name = '{$escTb}'
        LIMIT 1
    ");
    if ($check->num_rows === 0) {
        throw new RuntimeException("Tabel {$tableName} tidak ditemukan.");
    }

    // Ambil kolom goal
    $goalQ = $connection->query("
        SELECT atribut_id, atribut_name
        FROM atribut
        WHERE user_id = {$userId} AND goal = 1
        LIMIT 1
    ");
    if ($goalQ->num_rows === 0) {
        throw new RuntimeException('Atribut goal belum ditentukan untuk user ini.');
    }
    $goalRow    = $goalQ->fetch_assoc();
    $goalWanted = $goalRow['atribut_id'] . '_' . $goalRow['atribut_name'];

    // Ambil data (prefer algoritma SVM, fallback semua)
    $dataQ = $connection->query("
        SELECT * FROM `{$tableName}` WHERE algoritma = 'Support Vector Machine'
    ");
    if ($dataQ->num_rows === 0) {
        $logs[] = "ℹ️ Data dengan algoritma 'Support Vector Machine' tidak ditemukan, menggunakan seluruh dataset.";
        $dataQ = $connection->query("SELECT * FROM `{$tableName}`");
    }
    if ($dataQ->num_rows === 0) {
        throw new RuntimeException("Tidak ada data pada tabel {$tableName}.");
    }

    // Cari goalKey aktual dari daftar kolom (pakai row pertama)
    $firstRow = $dataQ->fetch_assoc();
    if (!$firstRow) {
        throw new RuntimeException('Dataset kosong setelah fetch.');
    }
    $columns = array_keys($firstRow);
    $goalKey = findGoalKey($columns, $goalWanted);
    if ($goalKey === null) {
        throw new RuntimeException(
            "Kolom goal '{$goalWanted}' tidak ditemukan. Kolom tersedia: ".implode(',', $columns)
        );
    }
    // reset pointer ke awal untuk iterasi
    $dataQ->data_seek(0);

    // Build dataset (fitur = semua kolom kecuali id/user/case_num/algoritma/goal)
    $samples = [];
    $labels  = [];
    $totalRows = 0;
    $skipNoLabel = 0;
    $skipZeroFeat = 0;

    while ($row = $dataQ->fetch_assoc()) {
        $totalRows++;

        $label = $row[$goalKey] ?? null;
        if ($label === null || $label === '') {
            $skipNoLabel++;
            continue;
        }

        $sample = [];
        foreach ($row as $column => $value) {
            if (in_array($column, ['case_id','user_id','case_num','algoritma',$goalKey], true)) {
                continue;
            }
            // Ambil nilai apa adanya; NumericStringConverter/OneHotEncoder akan mengurus casting
            $sample[] = $value;
        }

        if (count($sample) === 0) {
            $skipZeroFeat++;
            continue;
        }

        $samples[] = $sample;
        $labels[]  = $label;
    }

    if (!$samples) {
        $msg = 'Dataset tidak memiliki sampel yang valid untuk dilatih. '.
               "total={$totalRows}, kosong_label={$skipNoLabel}, fitur_kosong={$skipZeroFeat}. ".
               "Pastikan ada kolom fitur selain {$goalKey} dan label tidak kosong.";
        throw new RuntimeException($msg);
    }

    // Dataset + transformasi
    $dataset = Labeled::build($samples, $labels);
    $dataset->apply(new NumericStringConverter());
    $dataset->apply(new OneHotEncoder());

    // Path model
    $storageDirectory = dirname(__DIR__, 2) . '/storage/app/svm';
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException("Gagal membuat direktori penyimpanan model: {$storageDirectory}");
    }
    $modelPath = $storageDirectory . "/model_user_{$userId}.rbx";

    // Training
    $estimator = new PersistentModel(
        new SVC(new RBF(), 1.0, 1e-3),
        new Filesystem($modelPath)
    );

    $start    = microtime(true);
    $estimator->train($dataset);
    $estimator->save();
    $duration = microtime(true) - $start;

    $uniqueLabels = array_values(array_unique($labels));

    // Log + JSON (JSON diletakkan paling akhir agar mudah di-parse controller)
    $logs[] = "✅ Model SVM untuk user {$userId} berhasil dilatih.";
    $logs[] = "💾 Model disimpan di: {$modelPath}";
    $logs[] = "⏱️ Waktu eksekusi: " . number_format($duration, 6) . " detik";
    $logs[] = "📊 Jumlah baris sumber: {$totalRows} (skip_label={$skipNoLabel}, skip_fitur={$skipZeroFeat})";
    $logs[] = "📊 Sampel terpakai: " . count($samples);
    $logs[] = "🔤 Label unik: " . implode(', ', $uniqueLabels);

    $summary = [
        'status'         => 'success',
        'user_id'        => $userId,
        'model_path'     => $modelPath,
        'execution_time' => $duration,
        'samples'        => count($samples),
        'unique_labels'  => $uniqueLabels,
        'goal_column'    => $goalKey,
    ];

    echo implode(PHP_EOL, $logs) . PHP_EOL
       . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    $connection->close();
    exit(0);
} catch (Throwable $e) {
    if ($connection instanceof mysqli) $connection->close();

    $logs[]  = '❌ ' . $e->getMessage();
    $summary = ['status'=>'error','message'=>$e->getMessage()];

    echo implode(PHP_EOL, $logs) . PHP_EOL
       . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

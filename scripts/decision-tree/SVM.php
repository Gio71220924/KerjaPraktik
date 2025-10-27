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

$logs = [];

try {
    if ($argc < 3) {
        throw new InvalidArgumentException('Parameter user_id dan case_num wajib diberikan.');
    }

    $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
    $dotenv->safeLoad();

    $userId = (int) $argv[1];
    $caseNum = (int) $argv[2]; // Saat ini belum digunakan, namun tetap diterima untuk konsistensi

    if ($userId <= 0) {
        throw new InvalidArgumentException('Parameter user_id tidak valid.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = (int) ($_ENV['DB_PORT'] ?? 3306);
    $database = $_ENV['DB_DATABASE'] ?? 'expertt';
    $username = $_ENV['DB_USERNAME'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';

    $connection = new mysqli($host, $username, $password, $database, $port);
    $connection->set_charset('utf8mb4');

    $tableName = sprintf('test_case_user_%d', $userId);
    $escapedTableName = $connection->real_escape_string($tableName);
    $tableExists = $connection->query("SHOW TABLES LIKE '{$escapedTableName}'");

    if ($tableExists->num_rows === 0) {
        throw new RuntimeException("Tabel {$tableName} tidak ditemukan.");
    }

    $goalQuery = $connection->query(
        "SELECT atribut_id, atribut_name FROM atribut WHERE user_id = {$userId} AND goal = 1 LIMIT 1"
    );

    if ($goalQuery->num_rows === 0) {
        throw new RuntimeException('Atribut goal belum ditentukan untuk user ini.');
    }

    $goalRow = $goalQuery->fetch_assoc();
    $goalColumn = $goalRow['atribut_id'] . '_' . $goalRow['atribut_name'];

    $dataQuery = $connection->query(
        "SELECT * FROM `{$tableName}` WHERE algoritma = 'Support Vector Machine'"
    );

    if ($dataQuery->num_rows === 0) {
        $logs[] = "ℹ️ Data dengan algoritma 'Support Vector Machine' tidak ditemukan, menggunakan seluruh dataset.";
        $dataQuery = $connection->query("SELECT * FROM `{$tableName}`");
    }

    if ($dataQuery->num_rows === 0) {
        throw new RuntimeException("Tidak ada data pada tabel {$tableName}.");
    }

    $samples = [];
    $labels = [];

    while ($row = $dataQuery->fetch_assoc()) {
        if (!array_key_exists($goalColumn, $row)) {
            throw new RuntimeException("Kolom goal {$goalColumn} tidak ditemukan pada dataset.");
        }

        $label = $row[$goalColumn];
        if ($label === null || $label === '') {
            continue;
        }

        $sample = [];

        foreach ($row as $column => $value) {
            if (in_array($column, ['case_id', 'user_id', 'case_num', 'algoritma', $goalColumn], true)) {
                continue;
            }

            $sample[] = $value;
        }

        if (count($sample) === 0) {
            continue;
        }

        $samples[] = $sample;
        $labels[] = $label;
    }

    if (count($samples) === 0) {
        throw new RuntimeException('Dataset tidak memiliki sampel yang valid untuk dilatih.');
    }

    $dataset = Labeled::build($samples, $labels);
    $dataset->apply(new NumericStringConverter());
    $dataset->apply(new OneHotEncoder());

    $storageDirectory = dirname(__DIR__, 2) . '/storage/app/svm';
    if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
        throw new RuntimeException("Gagal membuat direktori penyimpanan model: {$storageDirectory}");
    }

    $modelPath = $storageDirectory . "/model_user_{$userId}.rbx";

    $estimator = new PersistentModel(
        new SVC(kernel: new RBF(), c: 1.0, tolerance: 1e-3),
        new Filesystem($modelPath)
    );

    $start = microtime(true);
    $estimator->train($dataset);
    $estimator->save();
    $duration = microtime(true) - $start;

    $uniqueLabels = array_values(array_unique($labels));

    $logs[] = "✅ Model SVM untuk user {$userId} berhasil dilatih.";
    $logs[] = "💾 Model disimpan di: {$modelPath}";
    $logs[] = "⏱️ Waktu eksekusi: " . number_format($duration, 6) . " detik";
    $logs[] = "📊 Jumlah sampel: " . count($samples);
    $logs[] = "🔤 Label unik: " . implode(', ', $uniqueLabels);

    $summary = [
        'status' => 'success',
        'user_id' => $userId,
        'model_path' => $modelPath,
        'execution_time' => $duration,
        'samples' => count($samples),
        'unique_labels' => $uniqueLabels,
    ];

    echo implode(PHP_EOL, $logs) . PHP_EOL . json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $connection->close();
    exit(0);
} catch (Throwable $exception) {
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }

    $logs[] = '❌ ' . $exception->getMessage();

    $summary = [
        'status' => 'error',
        'message' => $exception->getMessage(),
    ];

    echo implode(PHP_EOL, $logs) . PHP_EOL . json_encode($summary, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(1);
}

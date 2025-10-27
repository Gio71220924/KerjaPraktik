<?php

declare(strict_types=1);

use Rubix\ML\Classifiers\SVC;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Kernels\SVM\RBF;
use Rubix\ML\PersistentModel;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\Transformers\OneHotEncoder;

require_once __DIR__ . '/../../vendor/autoload.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASSWORD = '';
const DB_NAME = 'expertt';
const DB_PORT = 3307;

/**
 * Output helper that prints a JSON payload before terminating the script.
 *
 * @param array<string, mixed> $payload
 */
function respond(array $payload, int $exitCode = 0): void
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($exitCode);
}

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('This script must be executed from the CLI.');
    }

    if ($argc < 3) {
        throw new InvalidArgumentException('Missing required arguments: user_id and case_num.');
    }

    $userId = (int) $argv[1];
    $caseNum = (int) $argv[2];

    if ($userId <= 0) {
        throw new InvalidArgumentException('The provided user_id is invalid.');
    }

    $startTime = microtime(true);

    $connection = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    $connection->set_charset('utf8mb4');

    $caseTable = sprintf('test_case_user_%d', $userId);
    $caseQuery = sprintf('SELECT * FROM `%s`', $caseTable);

    $result = $connection->query($caseQuery);

    if ($result === false || $result->num_rows === 0) {
        throw new RuntimeException(sprintf('Tidak ada data di tabel %s', $caseTable));
    }

    $projectRoot = dirname(__DIR__, 2);
    $svmStoragePath = $projectRoot . '/storage/app/svm';
    if (!is_dir($svmStoragePath) && !mkdir($svmStoragePath, 0775, true) && !is_dir($svmStoragePath)) {
        throw new RuntimeException(sprintf('Tidak dapat membuat direktori penyimpanan SVM: %s', $svmStoragePath));
    }

    $csvPath = $svmStoragePath . sprintf('/dataset_user_%d.csv', $userId);
    $csvHandle = fopen($csvPath, 'w');

    if ($csvHandle === false) {
        throw new RuntimeException('Gagal membuat file dataset sementara.');
    }

    $fields = $result->fetch_fields();
    $header = [];
    foreach ($fields as $field) {
        $header[] = $field->name;
    }
    fputcsv($csvHandle, $header);

    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        fputcsv($csvHandle, $row);
    }
    fclose($csvHandle);

    $dataset = Labeled::fromIterator(new CSV($csvPath, true));
    $dataset->apply(new NumericStringConverter());
    $dataset->apply(new OneHotEncoder());

    $modelPath = $svmStoragePath . sprintf('/model_user_%d.rbx', $userId);

    $model = new PersistentModel(
        new SVC(kernel: new RBF(), c: 1.0, tolerance: 1e-3),
        new Filesystem($modelPath)
    );

    $model->train($dataset);
    $model->save();

    $endTime = microtime(true);
    $duration = $endTime - $startTime;

    // Bersihkan dataset sementara setelah pelatihan berhasil.
    if (file_exists($csvPath)) {
        unlink($csvPath);
    }

    $result->free();
    $connection->close();

    respond([
        'status' => 'success',
        'message' => sprintf('Model SVM user %d berhasil dilatih.', $userId),
        'user_id' => $userId,
        'case_num' => $caseNum,
        'model_path' => $modelPath,
        'duration' => $duration,
        'row_count' => $result->num_rows,
    ]);
} catch (Throwable $exception) {
    if (isset($result) && $result instanceof mysqli_result) {
        $result->free();
    }

    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }

    respond([
        'status' => 'error',
        'message' => $exception->getMessage(),
    ], 1);
}

<!-- Kode buat model SVM nya -->

<?php

require base_path('vendor/autoload.php');

use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Transformers\OneHotEncoder;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\Classifiers\SVC;
use Rubix\ML\Kernels\SVM\RBF;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;

//Hubungkan ke Database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "expertt";

// Buat Koneksi
$conn = new mysqli($servername, $username, $password, $dbname, 3307);
// Cek Koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
    }
// menangkap user_id yang aktif
$user_id = $argv[1];
$case_num = $argv[2];
$awal = microtime(true);

// Tentukan tabel case user
$table_name = "test_case_user_" . $user_id;

// Ambil semua data dari tabel
$query = "SELECT * FROM $table_name";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    die("❌ Tidak ada data di tabel $table_name\n");
}

// Buat file CSV sementara untuk Rubix
$csv_path = __DIR__ . "/dataset_user_{$user_id}.csv";
$file = fopen($csv_path, 'w');

// Tulis header kolom
$fields = $result->fetch_fields();
$header = [];
foreach ($fields as $field) {
    $header[] = $field->name;
}
fputcsv($file, $header);

// Reset pointer result
$result->data_seek(0);

// Tulis isi data
while ($row = $result->fetch_assoc()) {
    fputcsv($file, $row);
}

fclose($file);
echo "✅ Dataset berhasil dibuat: {$csv_path}\n";

// ============ TRAINING SVM ==============
$dataset = Labeled::fromIterator(new CSV($csv_path, true));
$dataset->apply(new NumericStringConverter());
$dataset->apply(new OneHotEncoder());

$modelPath = __DIR__ . "/model_user_{$user_id}.rbx";

$estimator = new PersistentModel(
    new SVC(kernel: new RBF(), c: 1.0, tolerance: 1e-3),
    new Filesystem($modelPath)
);

$estimator->train($dataset);
$estimator->save();

$akhir = microtime(true);
$lama = $akhir - $awal;

echo "✅ Model SVM user {$user_id} berhasil dilatih dan disimpan di {$modelPath}\n";
echo "⏱️ Waktu eksekusi: " . number_format($lama, 6) . " detik\n";

// Simpan hasil training ke tabel log (svm_user_{id})
$svm_table = "svm_user_" . $user_id;
$create = "
CREATE TABLE IF NOT EXISTS $svm_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(255),
    waktu FLOAT,
    model_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create);

$insert = $conn->prepare("INSERT INTO $svm_table (status, waktu, model_path) VALUES (?, ?, ?)");
$status = "SVM trained successfully";
$insert->bind_param("sds", $status, $lama, $modelPath);
$insert->execute();

echo "📊 Log training tersimpan di tabel: {$svm_table}\n";

$conn->close();



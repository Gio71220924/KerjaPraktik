<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rubix\ML\Classifiers\SVC;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Kernels\SVM\RBF;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\PersistentModel;
use Rubix\ML\Transformers\NumericStringConverter;
use Rubix\ML\Transformers\OneHotEncoder;

class SVMTrainer
{
    /**
     * Train an SVM model for the authenticated user and persist the metadata.
     */
    public function trainForUser(int $userId): array
    {
        $sourceTable = 'test_case_user_' . $userId;

        if (! Schema::hasTable($sourceTable)) {
            throw new \RuntimeException("Tabel {$sourceTable} tidak ditemukan. Harap buat data kasus terlebih dahulu.");
        }

        $records = DB::table($sourceTable)->get();

        if ($records->isEmpty()) {
            throw new \RuntimeException("Tabel {$sourceTable} belum memiliki data. Tambahkan data sebelum melatih SVM.");
        }

        $dataset = $this->buildDataset($records);

        $start = microtime(true);

        $modelPath = $this->determineModelPath($userId);
        $estimator = new PersistentModel(
            new SVC(kernel: new RBF(), c: 1.0, tolerance: 1e-3),
            new Filesystem($modelPath)
        );

        $estimator->train($dataset);
        $estimator->save();

        $duration = microtime(true) - $start;

        $logTable = $this->ensureLogTableExists($userId);

        $summary = $this->buildSummary($sourceTable, $records, $modelPath, $duration);

        DB::table($logTable)->insert([
            'status' => 'SVM trained successfully',
            'waktu' => $duration,
            'model_path' => $modelPath,
            'output' => $summary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'duration' => $duration,
            'model_path' => $modelPath,
            'summary' => $summary,
        ];
    }

    /**
     * Build a labeled dataset from database records.
     */
    private function buildDataset(Collection $records): Labeled
    {
        $first = (array) $records->first();

        // Ambil urutan kolom berdasarkan hasil query database.
        $columns = array_keys($first);

        $labelColumn = null;
        foreach (['hasil', 'goal', 'label', 'target'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $labelColumn = $candidate;
                break;
            }
        }

        if ($labelColumn === null) {
            $labelColumn = array_pop($columns);
        } else {
            $columns = array_values(array_filter($columns, fn ($column) => $column !== $labelColumn));
        }

        $samples = [];
        $labels = [];

        foreach ($records as $record) {
            $row = (array) $record;
            $labels[] = $row[$labelColumn];
            $samples[] = array_values(array_intersect_key($row, array_flip($columns)));
        }

        $dataset = Labeled::build($samples, $labels);
        $dataset->apply(new NumericStringConverter());
        $dataset->apply(new OneHotEncoder());

        return $dataset;
    }

    /**
     * Ensure the log table exists for the given user.
     */
    private function ensureLogTableExists(int $userId): string
    {
        $table = 'svm_user_' . $userId;

        if (! Schema::hasTable($table)) {
            Schema::create($table, function (Blueprint $table) {
                $table->id();
                $table->string('status');
                $table->float('waktu')->nullable();
                $table->string('model_path')->nullable();
                $table->text('output')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn($table, 'waktu')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->float('waktu')->nullable();
                });
            }

            if (! Schema::hasColumn($table, 'model_path')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->string('model_path')->nullable();
                });
            }

            if (! Schema::hasColumn($table, 'output')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->text('output')->nullable();
                });
            }
        }

        return $table;
    }

    /**
     * Determine the path where the model will be persisted.
     */
    private function determineModelPath(int $userId): string
    {
        $directory = storage_path('app/svm');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory . DIRECTORY_SEPARATOR . 'model_user_' . $userId . '.rbx';
    }

    /**
     * Build a human-readable summary about the training session.
     */
    private function buildSummary(string $sourceTable, Collection $records, string $modelPath, float $duration): string
    {
        $samples = $records->count();
        $featureCount = max(count((array) $records->first()) - 1, 0);

        $lines = [
            '✅ Dataset berhasil dimuat dari tabel ' . $sourceTable,
            '📦 Jumlah sampel: ' . $samples,
            '🔢 Jumlah fitur: ' . $featureCount,
            '💾 Model disimpan di: ' . $modelPath,
            '⏱️ Waktu eksekusi: ' . number_format($duration, 6) . ' detik',
        ];

        return implode(PHP_EOL, $lines);
    }
}


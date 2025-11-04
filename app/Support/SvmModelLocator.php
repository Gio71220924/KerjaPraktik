<?php

namespace App\Support;

class SvmModelLocator
{
    /**
     * Directories that may contain persisted SVM models. Mirrors the logic
     * used by the CLI trainer (scripts/decision-tree/SVM.php) so controllers
     * and the CLI stay in sync.
     *
     * @return array<int, string>
     */
    public static function directories(): array
    {
        $dirs = [];

        if (function_exists('base_path')) {
            $dirs[] = base_path('svm_models');
        } else {
            $dirs[] = getcwd() . DIRECTORY_SEPARATOR . 'svm_models';
        }

        if (function_exists('storage_path')) {
            $dirs[] = storage_path('app/svm');
        }

        // Remove empty values and duplicates while preserving order.
        $unique = [];
        foreach ($dirs as $dir) {
            if (!$dir) {
                continue;
            }
            if (!in_array($dir, $unique, true)) {
                $unique[] = $dir;
            }
        }

        return $unique;
    }

    public static function locate(string $fileName): ?string
    {
        foreach (self::directories() as $dir) {
            $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}


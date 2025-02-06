<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunBuatData extends Command
{
    protected $signature = 'run:buat_data';
    protected $description = 'Menjalankan buat data case';

    public function handle()
    {
        $this->info('Menjalankan pembuatan data case...');

        $output = shell_exec('php "' . base_path('scripts/decision-tree/buat_data_case.php') . '"');

        // Tampilkan hasil dari script c45.php
        $this->info('Hasil dari data case:');
        $this->info($output);
    }
}

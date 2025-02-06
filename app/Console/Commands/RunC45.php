<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunC45 extends Command
{
    protected $signature = 'run:c45';
    protected $description = 'Menjalankan algoritma C4.5 untuk membentuk pohon keputusan';

    public function handle()
    {
        $this->info('Menjalankan algoritma C4.5...');

        $output = shell_exec('php "' . base_path('scripts/decision-tree/c45.php') . '"');

        // Tampilkan hasil dari script c45.php
        $this->info('Hasil dari pohon keputusan:');
        $this->info($output);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunRule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:rule';
    protected $description = 'Menjalankan rule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Menjalankan rule...');

        $output = shell_exec('php "' . base_path('scripts/decision-tree/rule.php') . '"');

        // Tampilkan hasil dari script c45.php
        $this->info('Hasil dari rule:');
        $this->info($output);
    }
}

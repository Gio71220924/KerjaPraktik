<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunBC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:bc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan inference backward chaining';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Menjalankan inference...');

        $output = shell_exec('php "' . base_path('scripts/decision-tree/bc.php') . '"');

        // Tampilkan hasil dari script c45.php
        $this->info('Hasil dari inference:');
        $this->info($output);
    }
}

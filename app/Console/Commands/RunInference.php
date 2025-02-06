<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunInference extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:inference';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan inference matching rule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Menjalankan inference...');

        $output = shell_exec('php "' . base_path('scripts/decision-tree/matching_rule.php') . '"');

        // Tampilkan hasil dari script c45.php
        $this->info('Hasil dari inference:');
        $this->info($output);
    
    }
}

<?php

namespace App\Console;

use App\Console\Commands\RunBC;
use App\Console\Commands\RunBuatData;
use App\Console\Commands\RunRule;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\RunC45; // Import command RunC45
use App\Console\Commands\RunFC;
use App\Console\Commands\RunInference;

class Kernel extends ConsoleKernel
{
    // Daftarkan perintah custom
    protected $commands = [
        RunC45::class, // Daftarkan perintah RunC45 di sini
        RunRule::class, // Daftarkan perintah RunC45 di sini
        RunInference::class,
        RunBuatData::class,
        RunFC::class,
        RunBC::class,
    ];

    // Menjadwalkan perintah (Jika perlu)
    protected function schedule(Schedule $schedule)
    {
        // Contoh untuk menjadwalkan tugas
        // $schedule->command('run:c45')->daily();
    }
}

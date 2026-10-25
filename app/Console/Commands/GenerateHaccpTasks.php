<?php

namespace App\Console\Commands;

use App\Services\HaccpTaskGenerator;
use Illuminate\Console\Command;

class GenerateHaccpTasks extends Command
{
    protected $signature = 'haccp:generate-tasks';
    protected $description = 'Erzeugt fällige HACCP-Kontrollaufgaben aus den aktiven Kontrollplänen.';

    public function handle(HaccpTaskGenerator $generator): int
    {
        $count = $generator->generate();
        $this->info("{$count} HACCP-Aufgabe(n) erzeugt.");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Http\Controllers\DashboardController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class CheckDeployment extends Command
{
    protected $signature = 'app:check-deployment';

    protected $description = 'Prüft Projektpfad, Dashboard-Route und gebaute Frontend-Dateien.';

    public function handle(): int
    {
        $this->line('Projektpfad: '.base_path());
        $this->line('Plesk-Dokumentenstamm muss sein: '.public_path());
        $dashboard = Route::getRoutes()->getByName('dashboard');
        $action = $dashboard?->getActionName();
        $this->line('Dashboard: '.($action ?? 'FEHLT'));
        $valid = $dashboard?->uri() === 'dashboard' && $action === DashboardController::class.'@show';
        if (! $valid) {
            $this->error('Veraltete Dashboard-Route. php artisan optimize:clear ausführen und Projektpfad prüfen.');
        }
        $manifest = json_decode(@file_get_contents(public_path('build/manifest.json')) ?: '{}', true);
        foreach (['resources/js/app.jsx', 'resources/js/Pages/TimeTracking/Dashboard.jsx'] as $entry) {
            $file = $manifest[$entry]['file'] ?? null;
            if (! $file || ! is_file(public_path('build/'.$file))) {
                $this->error('Frontend fehlt: '.$entry.' — npm ci und npm run build ausführen.');
                $valid = false;
            }
        }
        if (is_file(public_path('hot'))) {
            $this->warn('public/hot vorhanden: Laravel verwendet den Vite-Entwicklungsserver statt public/build. Auf Produktion entfernen.');
            $valid = false;
        }
        if (! $valid) {
            return self::FAILURE;
        }
        $this->info('Dashboard-Route und Frontend-Dateien sind vorhanden. Wenn die Website abweicht, Plesk-Zielverzeichnis und PHP-FPM/OPcache prüfen.');

        return self::SUCCESS;
    }
}

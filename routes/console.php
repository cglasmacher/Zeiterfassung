<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('info:sync-resmio', function (\App\Services\Info\ResmioSync $sync) {
    try {
        $this->info('Synchronisiert: '.$sync->run());
    } catch (\Throwable $e) {
        $this->error(\App\Exceptions\ResmioSyncException::messageFor($e));
        return 1;
    }
});

Artisan::command('haccp:generate-extended', function (\App\Services\HaccpCleaningScheduler $cleaning, \App\Services\HaccpRandomSampleScheduler $samples) {
    $cleaningCount=$cleaning->generate();
    $sampleCount=$samples->ensureMonth((int)now()->format('Y'),(int)now()->format('n'));
    $this->info("Reinigungen: {$cleaningCount}; Stichproben neu geplant: {$sampleCount}");
});

Schedule::command('info:sync-resmio')->everyFiveMinutes()->withoutOverlapping()->when(fn () => \App\Models\InfoIntegration::settings()->enabled);
Schedule::command('haccp:generate-tasks')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('haccp:generate-extended')->dailyAt('00:07')->withoutOverlapping();

Artisan::command('info:admin {email} {--revoke}', function () {
    $user = \App\Models\User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Benutzer nicht gefunden.');
        return 1;
    }
    $user->forceFill(['is_info_admin' => ! $this->option('revoke')])->save();
    $this->info('Infosystem-Berechtigung aktualisiert.');
});

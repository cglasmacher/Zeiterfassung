<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Artisan::command('info:sync-resmio', function (\App\Services\Info\ResmioSync $sync) {
    try {
        $this->info('Synchronisiert: '.$sync->run());
    } catch (\Throwable $e) {
        $this->error(\App\Exceptions\ResmioSyncException::messageFor($e));

        return 1;
    }
});
\Illuminate\Support\Facades\Schedule::command('info:sync-resmio')->everyFiveMinutes()->withoutOverlapping()->when(fn () => \App\Models\InfoIntegration::settings()->enabled);

Artisan::command('info:admin {email} {--revoke}', function () {
    $user = \App\Models\User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Benutzer nicht gefunden.');

        return 1;
    }
    $user->forceFill(['is_info_admin' => ! $this->option('revoke')])->save();
    $this->info('Infosystem-Berechtigung aktualisiert.');
});

<?php

namespace App\Jobs;

use App\Services\Info\ResmioSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncInfoReservations implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $timeout = 180;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ResmioSync $sync): void
    {
        $sync->run();
    }
}

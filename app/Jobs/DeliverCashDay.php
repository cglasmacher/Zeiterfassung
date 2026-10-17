<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DeliverCashDay implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(): void
    {
        Cache::lock('cash-delivery-'.$this->deliveryId, 90)->block(1, function () {
            $delivery = DB::table('cash_deliveries')->find($this->deliveryId);
            if (! $delivery || $delivery->status === 'delivered') {
                return;
            }
            DB::table('cash_deliveries')->where('id', $delivery->id)->increment('attempts');
            try {
                $url = config('cash.n8n.url');
                $token = config('cash.n8n.token');
                if (! $url || ! str_starts_with($url, 'https://') || ! $token) {
                    throw new \RuntimeException('n8n HTTPS-Webhook und Token sind noch nicht konfiguriert.');
                }
                $response = Http::withToken($token)->acceptJson()->withHeaders(['Idempotency-Key' => $delivery->event_id])
                    ->connectTimeout(10)->timeout(30)->post($url, json_decode($delivery->payload, true));
                if (! $response->successful() || $response->json('event_id') !== $delivery->event_id || $response->json('accepted') !== true) {
                    throw new \RuntimeException('Keine gültige Empfangsbestätigung von n8n (HTTP '.$response->status().').');
                }
                DB::table('cash_deliveries')->where('id', $delivery->id)->update(['status' => 'delivered', 'delivered_at' => now(), 'last_error' => null, 'updated_at' => now()]);
            } catch (\Throwable $exception) {
                // Never persist HTTP exception bodies or URLs containing credentials.
                $message = $exception instanceof \RuntimeException && ! ($exception instanceof \Illuminate\Http\Client\ConnectionException)
                    ? $exception->getMessage() : 'Verbindung zu n8n fehlgeschlagen.';
                DB::table('cash_deliveries')->where('id', $delivery->id)->update(['status' => 'failed', 'last_error' => $message, 'updated_at' => now()]);
                throw new \RuntimeException($message);
            }
        });
    }
}

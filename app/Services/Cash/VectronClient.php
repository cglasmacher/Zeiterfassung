<?php

namespace App\Services\Cash;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VectronClient
{
    private function request(string $path, array $query = []): array
    {
        $config = config('cash.vectron');
        foreach (['base_url', 'operator_id', 'authorization_token', 'api_token'] as $key) {
            if (empty($config[$key])) {
                throw ValidationException::withMessages(['vectron' => 'Vectron-Zugang unvollständig: '.$key.'. Zugangsdaten serverseitig konfigurieren.']);
            }
        }
        if (! str_starts_with($config['base_url'], 'https://')) {
            throw ValidationException::withMessages(['vectron' => 'Vectron benötigt eine HTTPS-Adresse.']);
        }
        $response = Http::acceptJson()->withHeaders([
            'X-Authorization-Token' => $config['authorization_token'],
            'X-API-Token' => $config['api_token'],
        ])->connectTimeout(10)->timeout(30)->get(rtrim($config['base_url'], '/').$path, $query);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['vectron' => 'Vectron-Abruf fehlgeschlagen (HTTP '.$response->status().'). Zugang und Freischaltung prüfen.']);
        }
        $data = $response->json();
        if (! is_array($data) || ! array_is_list($data)) {
            throw ValidationException::withMessages(['vectron' => 'Unerwartetes Antwortformat von Vectron.']);
        }

        return $data;
    }

    public function sites(): array
    {
        return $this->request('/v3/operator/'.rawurlencode(config('cash.vectron.operator_id', '')).'/sites');
    }

    public function sync(): array
    {
        $site = config('cash.vectron.site_id');
        if (! $site) {
            return ['sites' => $this->sites(), 'message' => 'Bitte die gewünschte VECTRON_SITE_ID serverseitig hinterlegen.'];
        }

        return Cache::lock('vectron-sync-'.$site, 180)->block(1, function () use ($site) {
            DB::table('vectron_import_states')->insertOrIgnore(['site_id' => $site, 'created_at' => now(), 'updated_at' => now()]);
            $cursor = DB::table('vectron_import_states')->where('site_id', $site)->value('cursor');
            $rows = $this->request('/v3/site/'.rawurlencode($site).'/transactions', $cursor ? ['id' => $cursor] : []);
            DB::transaction(function () use ($rows, $site, &$cursor) {
                foreach ($rows as $row) {
                    if (empty($row['invoiceId']) || empty($row['transactionAt'])) {
                        throw ValidationException::withMessages(['vectron' => 'Rechnung ohne ID oder Datum empfangen; Import nicht fortgeschrieben.']);
                    }
                    DB::table('vectron_transactions')->updateOrInsert(['invoice_id' => $row['invoiceId']], [
                        'site_id' => $site,
                        'transaction_at' => \Carbon\Carbon::parse($row['transactionAt'])->utc()->format('Y-m-d H:i:s'),
                        'payload' => json_encode($row, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now(),
                    ]);
                    $cursor = $row['invoiceId'];
                }
                DB::table('vectron_import_states')->where('site_id', $site)->update(['cursor' => $cursor, 'synced_at' => now(), 'updated_at' => now()]);
            });

            return ['imported' => count($rows), 'caught_up' => count($rows) === 0,
                'message' => count($rows) ? 'Paket importiert. Erneut abrufen, bis keine weiteren Rechnungen vorliegen.' : 'Aktuell keine weiteren Rechnungen verfügbar.'];
        });
    }
}

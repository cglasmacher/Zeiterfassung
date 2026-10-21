<?php

namespace App\Services\Info;

use App\Exceptions\ResmioSyncException;
use App\Models\InfoIntegration;
use App\Models\InfoReservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ResmioSync
{
    public function run(): int
    {
        return Cache::lock('info-resmio-sync', 180)->block(2, function () {
            $s = InfoIntegration::settings();
            if (! $s->enabled || ! $s->facility || ! $s->authorization) {
                throw new ResmioSyncException('resmio ist noch nicht vollständig eingerichtet.');
            }
            $s->update(['last_attempt_at' => now()]);
            try {
                $base = rtrim(config('infosystem.resmio_base_url'), '/');
                if (parse_url($base, PHP_URL_SCHEME) !== 'https') {
                    throw new ResmioSyncException('resmio benötigt HTTPS.');
                }
                $path = '/facility/'.rawurlencode($s->facility).'/booking/';
                $url = $base.$path;
                $rows = [];
                $seen = [];
                $started = microtime(true);
                $from = now()->timezone('Europe/Berlin')->subDay()->startOfDay();
                $until = now()->timezone('Europe/Berlin')->addDays(15)->startOfDay();
                for ($page = 0; $url; $page++) {
                    if ($page >= 100 || microtime(true) - $started > 150 || isset($seen[$url])) {
                        throw new ResmioSyncException('Ungültige resmio-Paginierung.');
                    }
                    // Never forward credentials to a pagination URL outside the configured API path.
                    if (! str_starts_with($url, $base.$path)) {
                        throw new ResmioSyncException('Ungültiges resmio-Seitenziel.');
                    }
                    $seen[$url] = true;
                    $response = Http::acceptJson()->withHeaders(['Authorization' => $s->authorization])
                        ->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(15)
                        ->get($url, $page === 0 ? ['date__gte' => $from->toDateString(), 'date__lte' => $until->copy()->subDay()->toDateString(), 'limit' => 100] : []);
                    if (! $response->successful()) {
                        throw ResmioSyncException::http($response->status());
                    }
                    $json = $response->json();
                    if (! is_array($json) || ! isset($json['objects']) || ! is_array($json['objects'])) {
                        throw new ResmioSyncException('Die API antwortet, liefert aber keine Reservierungsliste im erwarteten Format (objects). Antwortschema und API-Version müssen anhand der resmio-Dokumentation abgeglichen werden.');
                    }
                    foreach ($json['objects'] as $row) {
                        $rows[] = $this->normalize($row, $s->facility);
                    }
                    $next = $json['meta']['next'] ?? null;
                    if ($next && ! is_string($next)) {
                        throw new ResmioSyncException('Ungültige nächste Seite.');
                    }
                    $url = $next ? (str_starts_with($next, '/') ? 'https://'.parse_url($base, PHP_URL_HOST).$next : $next) : null;
                }
                DB::transaction(function () use ($rows, $s, $from, $until) {
                    $current = InfoIntegration::whereKey($s->id)->lockForUpdate()->first();
                    if (! $current->enabled || $current->facility !== $s->facility) {
                        throw new ResmioSyncException('Integration während Abgleich geändert.');
                    }
                    foreach ($rows as $row) {
                        $existing = InfoReservation::where('external_id', $row['external_id'])->first();
                        // A deletion webhook wins over an in-flight API response.
                        if ($existing?->status === 'deleted') {
                            continue;
                        }
                        InfoReservation::updateOrCreate(['external_id' => $row['external_id']], $row);
                    }
                    if (config('infosystem.resmio_reconcile_missing')) {
                        InfoReservation::where('facility', $s->facility)
                            ->where('starts_at', '>=', $from->copy()->utc())
                            ->where('starts_at', '<', $until->copy()->utc())
                            ->whereNotIn('external_id', array_column($rows, 'external_id'))
                            ->where('status', '!=', 'deleted')->update(['status' => 'removed']);
                    }
                    InfoReservation::where('starts_at', '<', now()->subDays(7))->delete();
                    $s->update(['last_success_at' => now(), 'last_error' => null]);
                });

                return count($rows);
            } catch (\Throwable $e) {
                // Do not store remote response bodies/headers or guest details in logs.
                $s->update(['last_error' => ResmioSyncException::messageFor($e)]);
                throw $e;
            }
        });
    }

    private function normalize(array $row, string $facility): array
    {
        if (! isset($row['id'], $row['date'], $row['num_people']) || ! is_numeric($row['num_people']) || (int) $row['num_people'] < 1) {
            throw new ResmioSyncException('Ungültige Reservierung.');
        }
        $date = is_numeric($row['date']) ? CarbonImmutable::createFromTimestampUTC($row['date']) : CarbonImmutable::parse($row['date'], 'Europe/Berlin')->utc();
        $tables = $row['tables'] ?? '';
        if (is_array($tables)) {
            $tables = implode(', ', array_map(fn ($t) => is_array($t) ? ($t['name'] ?? $t['id'] ?? '') : $t, $tables));
        }

        return ['external_id' => (string) $row['id'], 'facility' => $facility, 'starts_at' => $date,
            'people' => (int) $row['num_people'], 'guest_name' => mb_substr(trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')), 0, 255),
            'tables' => mb_substr((string) $tables, 0, 255), 'status' => strtolower((string) ($row['status'] ?? 'confirmed'))];
    }
}

<?php

namespace App\Services\Info;

use App\Exceptions\ResmioSyncException;
use App\Models\InfoIntegration;
use App\Models\InfoReservation;
use App\Services\Reservations\ReservationService;
use App\Services\Reservations\ResmioBookingData;
use App\Services\Reservations\ResmioBookingImport;
use Carbon\CarbonImmutable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
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
                if (parse_url($base, PHP_URL_SCHEME) !== 'https' || parse_url($base, PHP_URL_USER) || parse_url($base, PHP_URL_QUERY)) {
                    throw new ResmioSyncException('Die resmio-API-Basis muss eine direkte HTTPS-Adresse ohne Zugangsdaten oder Queryparameter sein.');
                }
                // Keep the existing encrypted column, but send a raw Api-Key, never Authorization.
                $key = trim($s->authorization);
                $key = preg_replace('/^(?:Api-Key\s*:\s*|Bearer\s+|Token\s+)/i', '', $key);
                if (! $key || preg_match('/\s/', $key)) {
                    throw new ResmioSyncException('Bitte den reinen resmio-API-Key ohne Leerzeichen oder Header-Bezeichnung neu speichern.');
                }
                $started = microtime(true);
                $from = CarbonImmutable::now('Europe/Berlin')->subDay()->startOfDay()->utc();
                $until = CarbonImmutable::now('Europe/Berlin')->addDays(max(1, min(730, (int) config('infosystem.resmio_sync_days', 366))))->startOfDay()->utc();
                $path = '/facility/'.rawurlencode($s->facility);
                $bookings = $this->collection($base.$path.'/bookings', $key, [
                    'date__gte' => $from->toIso8601ZuluString(),
                    'date__lt' => $until->toIso8601ZuluString(),
                    'limit' => 100,
                ], $started);
                $resources = $this->collection($base.$path.'/resources', $key, ['limit' => 100], $started);
                $tables = [];
                foreach ($resources as $resource) {
                    if (! is_array($resource) || ! isset($resource['id'], $resource['name']) || ! is_scalar($resource['id']) || ! is_string($resource['name'])) {
                        throw new ResmioSyncException('Unerwartetes resmio-Tischformat: id oder name fehlt.');
                    }
                    $tables[(string) $resource['id']] = $resource['name'];
                }
                $tagCatalog = [];
                $customerTagCatalog = [];
                if (collect($bookings)->contains(fn ($b) => ! empty($b['tags']))) {
                    $tagCatalog = ResmioBookingData::catalog($this->collection($base.$path.'/booking_tags', $key, ['limit' => 100], $started), $s->facility, 'booking_tags');
                }
                if (collect($bookings)->contains(fn ($b) => ! empty($b['customer_tags']))) {
                    $customerTagCatalog = ResmioBookingData::catalog($this->collection($base.$path.'/customer_tags', $key, ['limit' => 100], $started), $s->facility, 'customer_tags');
                }
                $rows = [];
                $bookRows = [];
                $seenBookings = [];
                foreach ($bookings as $booking) {
                    if (! is_array($booking)) {
                        throw new ResmioSyncException('Unerwartetes resmio-Buchungsformat.');
                    }
                    $normalized = app(ResmioBookingData::class)->normalize($booking, $s->facility, $tagCatalog, $customerTagCatalog);
                    if (isset($seenBookings[$normalized['id']])) {
                        throw new ResmioSyncException('Doppelte Buchungskennung in der resmio-Antwort. Bitte erneut abgleichen.');
                    }
                    $seenBookings[$normalized['id']] = true;
                    $bookRows[] = $normalized;
                    $rows[] = $this->normalize($booking, $s->facility, $tables);
                }
                DB::transaction(function () use ($rows, $bookRows, $resources, $s, $from, $until) {
                    $current = InfoIntegration::whereKey($s->id)->lockForUpdate()->first();
                    if (! $current->enabled || $current->facility !== $s->facility || $current->authorization !== $s->authorization) {
                        throw new ResmioSyncException('Integration während Abgleich geändert.');
                    }
                    app(ReservationService::class)->lock();
                    $import = app(ResmioBookingImport::class);
                    $import->resources($s->facility, ResmioBookingData::catalog($resources, $s->facility, 'resources'));
                    $import->apply($s->facility, $bookRows, $from, $until);
                    $bookingsById = collect($bookRows)->keyBy('id');
                    foreach ($rows as $row) {
                        if (DB::table('reservation_resmio_deletions')->where('facility', $s->facility)->where('external_id', $row['external_id'])->exists()) {
                            continue;
                        }
                        $incoming = $bookingsById->get($row['external_id']);
                        $source = \App\Models\ReservationBooking::where('resmio_facility', $s->facility)->where('resmio_id', $row['external_id'])->first();
                        if (! empty($incoming['updated']) && $source?->resmio_updated_at && CarbonImmutable::parse($incoming['updated'])->lt($source->resmio_updated_at)) {
                            continue;
                        }
                        $existing = InfoReservation::where('external_id', $row['external_id'])->first();
                        // A deletion webhook wins over an in-flight API response.
                        if ($existing?->status === 'deleted' && $existing->facility === $s->facility) {
                            continue;
                        }
                        InfoReservation::updateOrCreate(['external_id' => $row['external_id']], $row);
                    }
                    if (config('infosystem.resmio_reconcile_missing')) {
                        InfoReservation::where('facility', $s->facility)
                            ->where('starts_at', '>=', $from)->where('starts_at', '<', $until)
                            ->whereNotIn('external_id', array_column($rows, 'external_id'))
                            ->where('status', '!=', 'deleted')->update(['status' => 'removed']);
                    }
                    InfoReservation::where('starts_at', '<', now()->subDays(7))->delete();
                    $s->update(['last_success_at' => now(), 'last_error' => null]);
                });

                return count($rows);
            } catch (\Throwable $e) {
                $s->update(['last_error' => ResmioSyncException::messageFor($e)]);
                throw $e;
            }
        });
    }

    private function collection(string $endpoint, string $key, array $filters, float $started): array
    {
        $canonical = new Uri($endpoint);
        $url = $endpoint;
        $seen = [];
        $objects = [];
        for ($page = 0; $url; $page++) {
            if ($page >= 1000 || microtime(true) - $started > 150) {
                throw new ResmioSyncException('resmio-Paginierung oder maximale Laufzeit überschritten.');
            }
            $uri = new Uri($url);
            if ($uri->getScheme() !== $canonical->getScheme() || $uri->getHost() !== $canonical->getHost()
                || $uri->getPort() !== $canonical->getPort() || $uri->getUserInfo() !== ''
                || rtrim($uri->getPath(), '/') !== rtrim($canonical->getPath(), '/')) {
                throw new ResmioSyncException('Ungültiges resmio-Seitenziel.');
            }
            parse_str($uri->getQuery(), $query);
            // Enforce our date window on every page, even when next only contains an offset.
            $query = array_merge($query, $filters);
            ksort($query);
            $signature = http_build_query($query);
            if (isset($seen[$signature])) {
                throw new ResmioSyncException('Wiederholte resmio-Seite erkannt.');
            }
            $seen[$signature] = true;
            $response = Http::acceptJson()->withHeaders(['Api-Key' => $key, 'Content-Type' => 'application/json'])
                ->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(15)->get($endpoint, $query);
            if (! $response->successful()) {
                throw ResmioSyncException::http($response->status());
            }
            $json = $response->json();
            if (! is_array($json) || ! isset($json['objects']) || ! is_array($json['objects']) || ! array_is_list($json['objects'])) {
                throw new ResmioSyncException('Die API antwortet, liefert aber keine Liste im dokumentierten Format (objects). API-Version und Antwortschema prüfen.');
            }
            array_push($objects, ...$json['objects']);
            $next = $json['meta']['next'] ?? null;
            if ($next !== null && (! is_string($next) || $next === '')) {
                throw new ResmioSyncException('Ungültige nächste resmio-Seite.');
            }
            $url = $next === null ? null : (string) UriResolver::resolve($canonical, new Uri($next));
        }

        return $objects;
    }

    private function normalize(array $row, string $facility, array $tables): array
    {
        if (! isset($row['id'], $row['date'], $row['num']) || ! is_scalar($row['id'])
            || ! is_string($row['date']) || trim($row['date']) === ''
            || filter_var($row['num'], FILTER_VALIDATE_INT) === false || (int) $row['num'] < 0) {
            throw new ResmioSyncException('Unerwartetes resmio-Buchungsformat: id, date oder num fehlt oder ist ungültig.');
        }
        try {
            $date = CarbonImmutable::parse($row['date'], 'Europe/Berlin')->utc();
        } catch (\Throwable $e) {
            throw new ResmioSyncException('Eine resmio-Buchung enthält kein gültiges Datum.');
        }
        $status = strtolower((string) ($row['status'] ?? 'confirmed'));
        $status = match ($status) {
            'noshow' => 'no_show', 'canceled' => 'cancelled', default => $status
        };
        if ((int) $row['num'] === 0 && ! in_array($status, ['cancelled', 'no_show', 'deleted', 'finished'], true)) {
            throw new ResmioSyncException('Eine aktive resmio-Buchung enthält keine Gästeanzahl.');
        }
        if (isset($row['facility']) && $row['facility'] !== '/v1/facility/'.$facility) {
            throw new ResmioSyncException('Eine resmio-Buchung gehört nicht zur eingerichteten Facility.');
        }
        if (isset($row['facility_resources']) && ! is_array($row['facility_resources'])) {
            throw new ResmioSyncException('Ungültige resmio-Tischliste.');
        }
        if (isset($row['name']) && ! is_string($row['name'])) {
            throw new ResmioSyncException('Ungültiges resmio-Namensformat.');
        }
        $names = [];
        foreach ($row['facility_resources'] ?? [] as $reference) {
            if (! is_string($reference) || ! preg_match('~^/v1/facility/'.preg_quote($facility, '~').'/resources/(\d+)/?$~', $reference, $match)) {
                throw new ResmioSyncException('Eine resmio-Buchung enthält eine ungültige Tischreferenz.');
            }
            $names[] = $tables[$match[1]] ?? '#'.$match[1];
        }

        return ['external_id' => (string) $row['id'], 'facility' => $facility, 'starts_at' => $date,
            'people' => (int) $row['num'], 'guest_name' => mb_substr((string) ($row['name'] ?? ''), 0, 255),
            'tables' => mb_substr(implode(', ', $names), 0, 255), 'status' => $status];
    }
}

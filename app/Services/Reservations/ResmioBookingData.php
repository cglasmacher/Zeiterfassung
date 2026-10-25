<?php

namespace App\Services\Reservations;

use App\Exceptions\ResmioSyncException;
use Carbon\CarbonImmutable;

class ResmioBookingData
{
    public static function reference(string $value, string $facility, string $type): string
    {
        if (! preg_match('~^/v1/facility/'.preg_quote($facility, '~').'/'.preg_quote($type, '~').'/(\d+)/?$~', $value, $match)) {
            throw new ResmioSyncException('Ungültige resmio-Referenz für '.$type.'.');
        }
        if (strlen($match[1]) > 100) {
            throw new ResmioSyncException('Ungültige resmio-Kennung.');
        }

        return $match[1];
    }

    public static function catalog(array $rows, string $facility, string $type): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['name']) || ! is_scalar($row['id']) || strlen((string) $row['id']) > 100 || ! is_string($row['name']) || (isset($row['facility']) && $row['facility'] !== '/v1/facility/'.$facility)) {
                throw new ResmioSyncException('Ungültiges resmio-Verzeichnis für '.$type.'.');
            }
            $result[(string) $row['id']] = ['id' => (string) $row['id'], 'name' => $row['name']];
        }

        return $result;
    }

    public function normalize(array $row, string $facility, array $tags, array $customerTags): array
    {
        if (! isset($row['id'], $row['date'], $row['num']) || ! is_scalar($row['id']) || ! is_string($row['date']) || ! preg_match('/^\d{4}-\d{2}-\d{2}T/', $row['date']) || filter_var($row['num'], FILTER_VALIDATE_INT) === false || $row['num'] < 0 || $row['num'] > 65535) {
            throw new ResmioSyncException('Eine resmio-Buchung enthält ungültige Stammdaten.');
        }
        if (mb_strlen($facility) > 100 || strlen((string) $row['id']) > 100) {
            throw new ResmioSyncException('Ungültige resmio-Kennung.');
        }
        if (isset($row['facility']) && $row['facility'] !== '/v1/facility/'.$facility) {
            throw new ResmioSyncException('Eine Buchung gehört nicht zur eingerichteten resmio-Facility.');
        }
        try {
            $date = CarbonImmutable::parse($row['date'], 'Europe/Berlin');
            $updated = empty($row['updated']) ? null : CarbonImmutable::parse($row['updated'], 'Europe/Berlin');
        } catch (\Throwable $e) {
            throw new ResmioSyncException('Ungültiges Datum in einer resmio-Buchung.');
        }
        $result = ['id' => (string) $row['id'], 'date' => $date->toIso8601String(), 'num' => (int) $row['num']];
        if ($updated) {
            $result['updated'] = $updated->toIso8601String();
        }
        foreach (['name', 'phone', 'email', 'notes', 'comment', 'customer_notes', 'source', 'ref_num', 'alias', 'customer', 'status'] as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }
            if ($row[$field] !== null && (! is_string($row[$field]) || strlen($row[$field]) > 60000)) {
                throw new ResmioSyncException('Ungültiges Textfeld in einer resmio-Buchung: '.$field.'.');
            }
            if ($field === 'status' && mb_strlen($row[$field] ?? '') > 40) {
                throw new ResmioSyncException('Ungültiger resmio-Status.');
            }
            $result[$field] = $row[$field];
        }
        if (isset($row['booking_timespan'])) {
            if (filter_var($row['booking_timespan'], FILTER_VALIDATE_INT) === false || $row['booking_timespan'] < 1 || $row['booking_timespan'] > 10080) {
                throw new ResmioSyncException('Ungültige Aufenthaltsdauer in einer resmio-Buchung.');
            }
            $result['booking_timespan'] = (int) $row['booking_timespan'];
        }
        if (array_key_exists('walk_in', $row)) {
            if (! is_bool($row['walk_in'])) {
                throw new ResmioSyncException('Ungültige Walk-in-Angabe in einer resmio-Buchung.');
            }
            $result['walk_in'] = $row['walk_in'];
        }
        foreach (['tags' => ['booking_tags', $tags], 'customer_tags' => ['customer_tags', $customerTags], 'facility_resources' => ['resources', null]] as $field => [$type, $catalog]) {
            if (! array_key_exists($field, $row)) {
                continue;
            }
            if (! is_array($row[$field]) || ! array_is_list($row[$field])) {
                throw new ResmioSyncException('Ungültige resmio-Liste: '.$field.'.');
            }
            $values = [];
            foreach ($row[$field] as $reference) {
                if (! is_string($reference)) {
                    throw new ResmioSyncException('Ungültige resmio-Referenzliste.');
                }
                $id = self::reference($reference, $facility, $type);
                $values[$id] = $catalog === null ? $id : ($catalog[$id] ?? ['id' => $id, 'name' => '#'.$id]);
            }
            $result[$field] = array_values($values);
        }
        if (array_key_exists('booking_request_parameters', $row)) {
            if (! is_array($row['booking_request_parameters']) || strlen(json_encode($row['booking_request_parameters'])) > 60000) {
                throw new ResmioSyncException('Ungültige Zusatzfelder in einer resmio-Buchung.');
            }
            $result['booking_request_parameters'] = $row['booking_request_parameters'];
        }

        return $result;
    }
}

<?php

namespace App\Exceptions;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;

class ResmioSyncException extends RuntimeException
{
    public static function http(int $status): self
    {
        $message = match ($status) {
            401 => 'resmio hat den API-Zugang abgelehnt (HTTP 401). Reinen API-Key und dessen Freigabe prüfen.',
            403 => 'resmio verweigert den Zugriff (HTTP 403). API-Freigabe und Berechtigung für diese Facility prüfen.',
            404 => 'Die resmio-API-Adresse oder Facility wurde nicht gefunden (HTTP 404). API-Basis, Lesepfad und Facility-ID anhand der resmio-Dokumentation prüfen.',
            400, 422 => 'resmio hat die Anfrage abgelehnt (HTTP '.$status.'). API-Pfad und Filterparameter anhand der resmio-Dokumentation prüfen.',
            429 => 'resmio begrenzt die Anfragen (HTTP 429). Bitte später erneut versuchen.',
            default => $status >= 300 && $status < 400
                ? 'resmio antwortet mit einer Weiterleitung (HTTP '.$status.'). Die direkte API-Adresse muss geprüft werden.'
                : 'resmio antwortet mit HTTP '.$status.'. Bitte später erneut versuchen und bei anhaltendem Fehler die API-Adresse prüfen.',
        };

        return new self($message);
    }

    public static function messageFor(Throwable $error): string
    {
        return match (true) {
            $error instanceof self => $error->getMessage(),
            $error instanceof ConnectionException => 'Die Verbindung zu resmio ist fehlgeschlagen. DNS, TLS, ausgehende HTTPS-Verbindungen und Zeitüberschreitung am Server prüfen.',
            $error instanceof LockTimeoutException => 'Ein resmio-Abgleich läuft bereits. Bitte kurz warten und erneut versuchen.',
            $error instanceof DecryptException => 'Die gespeicherten API-Zugangsdaten konnten nicht entschlüsselt werden. APP_KEY prüfen; bei einem Schlüsselwechsel Zugangsdaten neu speichern.',
            default => 'Interner Fehler beim resmio-Abgleich. Serverkonfiguration und Datenbank prüfen. Der API-Token ist damit nicht als ungültig bestätigt.',
        };
    }
}

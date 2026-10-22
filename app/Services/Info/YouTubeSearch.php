<?php

namespace App\Services\Info;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class YouTubeSearch
{
    public function search(string $query, string $page = ''): array
    {
        $key = (string) config('infosystem.youtube_api_key');
        if ($key === '') {
            throw new \RuntimeException('Die YouTube-Suche ist noch nicht eingerichtet. Bitte YOUTUBE_API_KEY auf dem Server hinterlegen. Video-Links können bereits direkt geöffnet werden.');
        }

        // Cache only normalized public metadata. Neither the API key nor raw API errors leave the server.
        return Cache::remember('info-youtube:'.hash('sha256', $key.'|'.$query.'|'.$page), now()->addMinutes(10), function () use ($key, $query, $page) {
            try {
                $response = Http::acceptJson()->withHeaders(['X-Goog-Api-Key' => $key])
                    ->connectTimeout(4)->timeout(12)->withoutRedirecting()
                    ->get('https://www.googleapis.com/youtube/v3/search', array_filter([
                        'part' => 'snippet', 'type' => 'video', 'q' => $query, 'maxResults' => 12,
                        'videoEmbeddable' => 'true', 'videoSyndicated' => 'true',
                        'safeSearch' => 'moderate', 'regionCode' => 'DE', 'relevanceLanguage' => 'de',
                        'pageToken' => $page,
                    ], fn ($value) => $value !== ''));
            } catch (\Throwable) {
                throw new \RuntimeException('YouTube ist gerade nicht erreichbar. Bitte erneut versuchen.');
            }
            if (! $response->successful()) {
                $reason = $response->json('error.errors.0.reason');
                $message = match ($reason) {
                    'quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded' => 'Das YouTube-Suchlimit ist erreicht. Bitte später erneut versuchen oder einen Video-Link öffnen.',
                    'keyInvalid', 'accessNotConfigured', 'ipRefererBlocked', 'forbidden' => 'YouTube-API-Key oder API-Freigabe prüfen lassen. Die Suche ist momentan nicht verfügbar.',
                    default => 'Die YouTube-Suche ist momentan nicht verfügbar. Bitte API-Key und Freigabe der YouTube Data API v3 prüfen lassen.',
                };
                throw new \RuntimeException($message);
            }
            $rows = $response->json('items');
            if (! is_array($rows)) {
                throw new \RuntimeException('YouTube hat keine gültige Suchantwort geliefert. Bitte erneut versuchen.');
            }
            $items = [];
            foreach ($rows as $row) {
                $id = data_get($row, 'id.videoId');
                $title = data_get($row, 'snippet.title');
                $channel = data_get($row, 'snippet.channelTitle');
                if (! is_string($id) || ! preg_match('/^[\w-]{11}$/D', $id) || ! is_string($title) || ! is_string($channel)) {
                    continue;
                }
                $items[] = ['id' => $id, 'title' => html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'channel' => html_entity_decode($channel, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'thumbnail' => 'https://i.ytimg.com/vi/'.$id.'/mqdefault.jpg'];
            }
            $next = $response->json('nextPageToken');

            return ['items' => $items, 'next' => is_string($next) && preg_match('/^[a-zA-Z0-9_=-]{1,512}$/D', $next) ? $next : null];
        });
    }
}

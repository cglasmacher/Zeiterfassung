<?php

namespace Tests\Feature\Info;

use App\Models\InfoDisplay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YouTubeSearchTest extends TestCase
{
    use RefreshDatabase;

    private function connectDisplay(): void
    {
        $display = InfoDisplay::first();
        $display->update(['token_hash' => hash('sha256', 'tv-token')]);
        $this->withCredentials()->withCookie('info_display_'.$display->id, 'tv-token');
    }

    public function test_search_is_scoped_to_an_active_connected_display(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/display/kitchen/youtube/search?q=jazz')->assertForbidden();
        $this->connectDisplay();
        $this->getJson('/display/missing/youtube/search?q=jazz')->assertNotFound();
        InfoDisplay::first()->update(['token_hash' => hash('sha256', 'rotated')]);
        $this->getJson('/display/kitchen/youtube/search?q=jazz')->assertForbidden();
        InfoDisplay::first()->update(['active' => false]);
        $this->getJson('/display/kitchen/youtube/search?q=jazz')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_search_validates_input_and_explains_missing_configuration(): void
    {
        $this->connectDisplay();
        config(['infosystem.youtube_api_key' => '']);
        Http::preventStrayRequests();
        foreach (['q=x', 'q='.str_repeat('x', 121), 'q=jazz&page=https://evil.test'] as $query) {
            $this->getJson('/display/kitchen/youtube/search?'.$query)->assertUnprocessable();
        }
        $this->getJson('/display/kitchen/youtube/search?q=jazz')->assertStatus(503)->assertSee('YOUTUBE_API_KEY');
        Http::assertNothingSent();
    }

    public function test_search_uses_server_key_filters_video_results_paginates_and_caches(): void
    {
        $this->connectDisplay();
        config(['infosystem.youtube_api_key' => 'private-youtube-key']);
        Http::preventStrayRequests();
        Http::fake(['www.googleapis.com/youtube/v3/search*' => Http::response([
            'items' => [
                ['id' => ['videoId' => 'abcdefghijk'], 'snippet' => ['title' => 'Jazz &amp; Café', 'channelTitle' => 'Music &quot;Live&quot;', 'thumbnails' => ['medium' => ['url' => 'https://evil.test/image']]]],
                ['id' => ['playlistId' => 'playlist'], 'snippet' => ['title' => 'Playlist']],
                ['id' => ['videoId' => 'bad-id'], 'snippet' => ['title' => 'Invalid', 'channelTitle' => 'Bad']],
            ], 'nextPageToken' => 'Next_page-2=', 'secret' => 'private-youtube-key',
        ])]);
        $url = 'https://stream.hemingway-hilden.de/display/kitchen/youtube/search?q=jazz';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.title', 'Jazz & Café')
            ->assertJsonPath('items.0.thumbnail', 'https://i.ytimg.com/vi/abcdefghijk/mqdefault.jpg')
            ->assertJsonPath('next', 'Next_page-2=')->assertDontSee('private-youtube-key');
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
        $this->getJson($url.'&page=Next_page-2%3D')->assertOk();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-Api-Key', 'private-youtube-key')
            && ! str_contains($request->url(), 'private-youtube-key')
            && $request['part'] === 'snippet' && $request['type'] === 'video'
            && $request['videoEmbeddable'] === 'true' && $request['videoSyndicated'] === 'true'
            && ($request->data()['pageToken'] ?? null) === 'Next_page-2=');
        $this->getJson('/display/kitchen/data')->assertDontSee('private-youtube-key');
    }

    public function test_api_errors_are_actionable_redacted_and_not_cached(): void
    {
        $this->connectDisplay();
        config(['infosystem.youtube_api_key' => 'private-key']);
        Http::fakeSequence()->push(['error' => ['message' => 'private-key', 'errors' => [['reason' => 'quotaExceeded']]]], 403)
            ->push(['items' => []], 200);
        $url = '/display/kitchen/youtube/search?q=quota-test';
        $this->getJson($url)->assertStatus(503)->assertSee('Suchlimit')->assertDontSee('private-key');
        $this->getJson($url)->assertOk()->assertExactJson(['items' => [], 'next' => null]);
        Http::assertSentCount(2);
    }

    public function test_redirect_and_malformed_response_never_expose_upstream_content(): void
    {
        $this->connectDisplay();
        config(['infosystem.youtube_api_key' => 'private-key']);
        Http::fakeSequence()->push('', 302, ['Location' => 'https://evil.test'])
            ->push(['unexpected' => 'private-key'], 200);
        $this->getJson('/display/kitchen/youtube/search?q=redirect')->assertStatus(503)->assertDontSee('private-key');
        $this->getJson('/display/kitchen/youtube/search?q=malformed')->assertStatus(503)->assertDontSee('private-key');
        Http::assertSentCount(2);
    }
}

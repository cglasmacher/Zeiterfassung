<?php

namespace Tests\Feature\Info;

use App\Models\InfoIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResmioDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_error_is_actionable_without_exposing_remote_body_or_token(): void
    {
        $this->actingAs(User::factory()->create()->forceFill(['is_info_admin' => true]));
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'secret-api-token']);
        foreach ([401, 403, 404, 429, 500] as $status) {
            Http::swap(new \Illuminate\Http\Client\Factory);
            Http::fake(['*' => Http::response(['debug' => 'secret-api-token private guest information'], $status)]);
            $r = $this->postJson('/api/infosystem/sync')->assertStatus(502);
            $this->assertStringContainsString('HTTP '.$status, $r->json('message'));
            $this->assertStringNotContainsString('secret-api-token', $r->getContent());
            $this->assertStringNotContainsString('private guest', $r->getContent());
            $this->assertSame($r->json('message'), InfoIntegration::settings()->last_error);
        }
    }

    public function test_schema_mismatch_and_connection_failure_are_distinct(): void
    {
        $this->actingAs(User::factory()->create()->forceFill(['is_info_admin' => true]));
        InfoIntegration::settings()->update(['enabled' => true, 'facility' => 'hemingway', 'authorization' => 'secret']);
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->assertStringContainsString('objects', $this->postJson('/api/infosystem/sync')->assertStatus(502)->json('message'));
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Sensitive request details secret'));
        $r = $this->postJson('/api/infosystem/sync')->assertStatus(502);
        $this->assertStringContainsString('Verbindung', $r->json('message'));
        $this->assertStringNotContainsString('secret', $r->getContent());
    }

    public function test_unconfigured_integration_and_cli_show_safe_errors(): void
    {
        $this->actingAs(User::factory()->create()->forceFill(['is_info_admin' => true]));
        $this->postJson('/api/infosystem/sync')->assertStatus(502)->assertJsonPath('message', 'resmio ist noch nicht vollständig eingerichtet.');
        $this->artisan('info:sync-resmio')->expectsOutput('resmio ist noch nicht vollständig eingerichtet.')->assertFailed();
    }
}

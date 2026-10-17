<?php

namespace Tests\Feature\Workspace;

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    public function test_diagnostics_reject_the_old_starter_dashboard_route(): void
    {
        Route::getRoutes()->getByName('dashboard')->setAction(['uses' => fn () => 'Starter']);
        $this->artisan('app:check-deployment')
            ->expectsOutputToContain('Veraltete Dashboard-Route')
            ->assertExitCode(1);
    }

    public function test_dashboard_route_has_a_cacheable_controller_action(): void
    {
        $this->assertSame(DashboardController::class.'@show', Route::getRoutes()->getByName('dashboard')->getActionName());
        $this->assertSame('/api/dashboard', route('dashboard.data', absolute: false));
    }
}

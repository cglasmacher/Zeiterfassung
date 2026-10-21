<?php

namespace Tests\Feature\Access;

use App\Models\Employee;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function team(): User
    {
        return User::where('email', 'info@hemingway-hilden.de')->firstOrFail();
    }

    public function test_team_account_is_provisioned_with_only_operational_access(): void
    {
        $u = $this->team();
        $this->assertSame('operations', $u->access_role);
        $this->assertFalse($u->is_info_admin);
        $this->actingAs($u);
        foreach (['/kasse/tagesabschluss', '/kasse/uebergaben', '/kasse/gutscheinverkauf', '/dienstplan'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/kasse/tagesabschluss')->assertInertia(fn (Assert $p) => $p->has('allowedNavigation', 4)
            ->where('allowedNavigation', ['cash.handovers', 'cash.days', 'cash.vouchers', 'timetracking.planner']));
        $this->get('/')->assertRedirect(route('cash.days'));
    }

    public function test_direct_pages_and_admin_apis_are_forbidden_including_writes(): void
    {
        $this->actingAs($this->team());
        foreach (['/dashboard', '/infosystem', '/einstellungen', '/team/zeiten', '/team/monat', '/team/erfassung', '/team/korrekturen', '/exporte/lohn', '/buchhaltung/belege'] as $path) {
            $this->get($path)->assertForbidden();
        }
        foreach (['/api/dashboard', '/api/employees', '/api/departments', '/api/shift-types', '/api/settings', '/api/summary/monthly', '/api/daily-overview', '/api/manual-time/open-entries', '/api/time-manipulation/month-entries', '/api/accounting/documents', '/api/infosystem'] as $path) {
            $this->getJson($path)->assertForbidden();
        }
        foreach (['/api/employees', '/api/departments', '/api/shift-types', '/api/settings', '/api/manual-time/clock-in', '/api/time-manipulation/entries/1/split', '/api/lexware-export/export', '/api/daily-overview/recalculate-wages', '/api/infosystem/items'] as $path) {
            $this->postJson($path, [])->assertForbidden();
        }
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_planner_and_cash_api_dependencies_work_without_employee_administration(): void
    {
        $e = Employee::create(['first_name' => 'A', 'last_name' => 'B', 'hourly_rate' => 20, 'rfid_tag' => 'secret', 'email' => 'private@example.test']);
        $type = ShiftType::create(['name' => 'Früh', 'default_start' => '09:00', 'default_end' => '14:00', 'default_break_minutes' => 0, 'active' => true]);
        $this->actingAs($this->team());
        $response = $this->getJson('/api/shifts')->assertOk()->assertJsonPath('employees.0.first_name', 'A');
        foreach (['hourly_rate', 'rfid_tag', 'email', 'phone'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json('employees.0'));
        }
        $response = $this->postJson('/api/shifts', ['employee_id' => $e->id, 'shift_type_id' => $type->id, 'shift_date' => now()->toDateString()])->assertOk();
        $this->assertArrayNotHasKey('rfid_tag', $response->json('shift.employee'));
        $id = $response->json('shift.id');
        $this->deleteJson('/api/shifts/'.$id)->assertOk();
        $this->getJson('/api/shifts/templates')->assertOk();
        $this->getJson('/api/cash/days')->assertOk();
        $this->getJson('/api/cash/vouchers')->assertOk();
    }

    public function test_registration_and_profile_cannot_elevate_permissions(): void
    {
        $this->post('/register', ['name' => 'New', 'email' => 'new@example.test', 'password' => 'test-password', 'password_confirmation' => 'test-password', 'access_role' => 'admin', 'is_info_admin' => true])->assertRedirect('/profile');
        $u = User::where('email', 'new@example.test')->first();
        $this->assertSame('none', $u->access_role);
        $this->assertFalse($u->is_info_admin);
        $this->getJson('/api/cash/days')->assertForbidden();
        $this->patch('/profile', ['name' => 'New', 'email' => 'new@example.test', 'access_role' => 'admin', 'is_info_admin' => true])->assertSessionHasNoErrors();
        $this->assertSame('none', $u->fresh()->access_role);
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_team_login_ignores_forbidden_intended_location(): void
    {
        $this->team()->update(['password' => Hash::make('test-password')]);
        $this->withSession(['url.intended' => '/einstellungen'])->post('/login', ['email' => 'info@hemingway-hilden.de', 'password' => 'test-password'])->assertRedirect(route('cash.days'));
    }

    public function test_provision_command_preserves_password_and_is_idempotent(): void
    {
        $u = $this->team();
        $password = $u->password;
        $this->artisan('users:info')->assertSuccessful();
        $this->artisan('users:info')->assertSuccessful();
        $this->assertSame($password, $u->fresh()->password);
        $this->assertSame(1, User::where('email',$u->email)->count());
    }
}

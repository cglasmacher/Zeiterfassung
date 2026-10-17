<?php

namespace Tests\Feature\Workspace;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftType;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-17 11:00:00', 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_empty_dashboard_has_real_zeroes_and_no_fabricated_activity(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('stats.clocked_in', 0)->assertJsonPath('stats.active_employees', 0)
            ->assertJsonPath('stats.completed_hours_today', 0)->assertJsonPath('cash.balance_cents', null)
            ->assertJsonCount(0, 'attendance')->assertJsonCount(0, 'activities')->assertJsonCount(0, 'alerts');
    }

    public function test_overnight_presence_and_planned_future_shifts_are_not_false_absences(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = Employee::create(['first_name' => 'Overnight', 'last_name' => 'Worker', 'active' => true]);
        TimeEntry::create(['employee_id' => $employee->id, 'clock_in' => '2026-10-16 23:00:00']);
        $future = Employee::create(['first_name' => 'Future', 'last_name' => 'Worker', 'active' => true]);
        TimeEntry::create(['employee_id' => $future->id, 'clock_in' => '2026-10-17 18:00:00']);
        TimeEntry::create(['employee_id' => $employee->id, 'clock_in' => '2026-10-17 06:00:00', 'clock_out' => '2026-10-17 09:00:00', 'total_hours' => 3]);
        $type = ShiftType::create(['name' => 'Evening', 'default_start' => '18:00', 'default_end' => '23:00', 'active' => true]);
        Shift::create(['employee_id' => $future->id, 'shift_type_id' => $type->id, 'shift_date' => '2026-10-17', 'start_time' => '18:00', 'end_time' => '23:00', 'planned_hours' => 5, 'status' => 'planned']);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('stats.clocked_in', 1)
            ->assertJsonPath('stats.completed_hours_today', 3)->assertJsonPath('stats.shifts_today', 1)
            ->assertJsonPath('attendance.0.name', 'Overnight Worker')->assertJsonPath('attendance.0.elapsed_minutes', 720)
            ->assertJsonCount(0, 'alerts');
    }

    public function test_month_uses_selected_employee_and_entire_month_including_inactive_staff(): void
    {
        $this->actingAs(User::factory()->create());
        $first = Employee::create(['first_name' => 'First', 'last_name' => 'Person', 'active' => true]);
        $second = Employee::create(['first_name' => 'Second', 'last_name' => 'Person', 'active' => false]);
        foreach ([[$first->id, '2026-10-01 08:00:00', '2026-10-01 12:00:00', 4], [$second->id, '2026-10-31 18:00:00', '2026-10-31 21:00:00', 3], [$first->id, '2026-11-01 08:00:00', '2026-11-01 16:00:00', 8]] as [$id, $start, $end, $hours]) {
            TimeEntry::create(['employee_id' => $id, 'clock_in' => $start, 'clock_out' => $end, 'total_hours' => $hours, 'gross_wage' => $hours * 15]);
        }
        $this->getJson('/api/summary/monthly?year=2026&month=10')->assertOk()->assertJsonPath('totals.hours', 7)->assertJsonPath('totals.employees', 2);
        $this->getJson('/api/summary/monthly?year=2026&month=10&employee_id='.$second->id)->assertOk()->assertJsonPath('totals.hours', 3)->assertJsonPath('rows.0.name', 'Second Person')->assertJsonCount(1, 'entries');
        $this->getJson('/api/summary/monthly?year=2026&month=13')->assertUnprocessable();
    }

    public function test_default_seeder_does_not_create_demo_staff_or_shifts(): void
    {
        $this->seed();
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_admin_endpoints_require_login_and_device_routes_remain_available(): void
    {
        foreach (['/api/dashboard', '/api/employees', '/api/shifts', '/api/summary/monthly?year=2026&month=10', '/api/daily-overview', '/api/time-manipulation/month-entries'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $employee = Employee::create(['first_name' => 'Device', 'last_name' => 'User', 'rfid_tag' => 'TEST-DEVICE']);
        $this->getJson('/api/summary/current?rfid_tag=TEST-DEVICE')->assertOk()->assertJsonPath('employee', 'Device User');
        $this->get('/api/daily-overview/force-fix/1')->assertNotFound();
    }

    public function test_legacy_page_urls_redirect_to_the_single_canonical_pages(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/timetracking')->assertRedirect('/dashboard');
        $this->get('/timetracking/daily')->assertRedirect('/team/zeiten');
        $this->get('/timetracking/cash-days')->assertRedirect('/kasse/tagesabschluss');
        $this->get('/time-tracking/shift-planner')->assertRedirect('/dienstplan');
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('TimeTracking/Dashboard')
            ->where('initialData.stats.clocked_in', 0)
            ->has('initialData.attendance', 0)
            ->has('initialData.cash'));
        $this->get('/login')->assertRedirect('/dashboard');
        $this->get('/einstellungen?tab=employees')->assertOk();
        $this->get('/einstellungen?tab=missing')->assertNotFound();
    }
}

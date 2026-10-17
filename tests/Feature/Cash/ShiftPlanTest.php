<?php

namespace Tests\Feature\Cash;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_sort_order_persists_and_planning_handles_overnight_shifts(): void
    {
        $this->actingAs(User::factory()->create());
        $type = $this->postJson('/api/shift-types', ['name' => 'Spät', 'default_start' => '20:00', 'default_end' => '02:00', 'default_break_minutes' => 30, 'print_num' => 2, 'active' => true])->assertOk()->json();
        $this->assertSame(2, $type['print_num']);
        $this->postJson('/api/shift-types', ['name' => 'Früh', 'default_start' => '08:00', 'default_end' => '16:00', 'default_break_minutes' => 30, 'print_num' => 1, 'active' => true])->assertOk();
        $this->getJson('/api/shifts?start=2026-10-12&end=2026-10-18')->assertOk()->assertJsonPath('shift_types.0.name', 'Früh');
        $this->postJson('/api/shifts', ['shift_type_id' => $type['id'], 'shift_date' => '2026-10-16'])->assertOk()->assertJsonPath('shift.planned_hours', 5.5);
        $this->postJson('/api/shifts/export-pdf', ['week_start' => '2026-10-12'])->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_existing_payroll_preview_includes_the_entire_last_selected_day(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = \App\Models\Employee::create(['first_name' => 'Test', 'last_name' => 'Export', 'employment_type' => 'temporary', 'active' => true, 'hourly_rate' => 15]);
        \App\Models\TimeEntry::create(['employee_id' => $employee->id, 'clock_in' => '2026-10-31 18:00:00', 'clock_out' => '2026-10-31 22:00:00', 'total_hours' => 4, 'gross_wage' => 60]);
        $this->postJson('/api/lexware-export/preview', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'])->assertOk()->assertJsonPath('summary.total_hours', 4)->assertJsonPath('summary.total_wage', 60);
    }
}

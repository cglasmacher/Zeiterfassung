<?php

namespace Tests\Feature\Cash;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_grid_includes_four_configured_types_and_separate_seats(): void
    {
        $this->actingAs(User::factory()->create());
        $service = \App\Models\Department::create(['name' => 'Service']);
        $types = collect(range(1, 4))->map(function ($number) use ($service) {
            $type = \App\Models\ShiftType::create([
                'name' => 'Dienst '.$number, 'default_start' => '10:00', 'default_end' => '16:00',
                'default_break_minutes' => 0, 'active' => true, 'print_num' => 5 - $number,
            ]);
            $type->departments()->attach($service);
            return $type;
        });
        foreach (['Anna', 'Beate'] as $name) {
            $employee = \App\Models\Employee::create([
                'first_name' => $name, 'last_name' => 'Test', 'employment_type' => 'temporary',
                'active' => true, 'hourly_rate' => 15,
            ]);
            \App\Models\Shift::create([
                'employee_id' => $employee->id, 'department_id' => $service->id,
                'shift_type_id' => $types->first()->id, 'shift_date' => '2026-10-14',
                'start_time' => '11:00', 'end_time' => '16:00', 'status' => 'planned',
            ]);
        }
        \App\Models\Setting::set('closed_days', [2, 3]);

        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->withArgs(function ($view, $data) {
                $this->assertSame('exports.shift-plan', $view);
                $this->assertCount(1, $data['pages']);
                $columns = $data['pages']->first();
                $this->assertCount(5, $columns);
                $this->assertSame(['Dienst 4', 'Dienst 3', 'Dienst 2', 'Dienst 1', 'Dienst 1'], $columns->pluck('type.name')->all());
                $this->assertSame('Anna', $columns->get(3)['assignments']->get('2026-10-14')->employee->first_name);
                $this->assertSame('Beate', $columns->get(4)['assignments']->get('2026-10-14')->employee->first_name);
                $this->assertSame([1, 3, 4, 5, 6, 0], $data['days']->map->dayOfWeek->all());
                $html = view($view, $data)->render();
                $this->assertStringContainsString('colspan="2"', $html);
                $this->assertStringContainsString('Anna Test', $html);
                $this->assertStringContainsString('11:00 - 16:00', $html);
                return true;
            })->andReturnSelf();
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('setPaper')->with('a4', 'landscape')->once()->andReturnSelf();
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('download')->once()->andReturn(response('PDF'));

        $this->postJson('/api/shifts/export-pdf', ['week_start' => '2026-10-12'])->assertOk();
    }

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

    public function test_pdf_keeps_inactive_planned_types_and_continues_large_grids(): void
    {
        $this->actingAs(User::factory()->create());
        $type = \App\Models\ShiftType::create([
            'name' => 'Alter Dienst', 'default_start' => '20:00', 'default_end' => '02:00',
            'default_break_minutes' => 0, 'active' => false,
        ]);
        foreach (range(1, 17) as $seat) {
            \App\Models\Shift::create([
                'shift_type_id' => $type->id, 'shift_date' => '2026-10-12',
                'start_time' => '20:00', 'end_time' => '02:00', 'status' => 'planned',
            ]);
        }
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->withArgs(function ($view, $data) {
                $this->assertCount(2, $data['pages']);
                $columns = $data['pages']->flatten(1);
                $this->assertCount(17, $columns);
                $this->assertSame(17, $columns->sum(fn ($column) => $column['assignments']->count()));
                $html = view($view, $data)->render();
                $this->assertSame(17, substr_count($html, 'Offen'));
                $this->assertStringContainsString('Ohne Bereich', $html);
                return true;
            })->andReturnSelf();
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('download')->once()->andReturn(response('PDF'));
        $this->postJson('/api/shifts/export-pdf', ['week_start' => '2026-10-12'])->assertOk();
    }

    public function test_existing_payroll_preview_includes_the_entire_last_selected_day(): void
    {
        $this->actingAs(User::factory()->create());
        $employee = \App\Models\Employee::create(['first_name' => 'Test', 'last_name' => 'Export', 'employment_type' => 'temporary', 'active' => true, 'hourly_rate' => 15]);
        \App\Models\TimeEntry::create(['employee_id' => $employee->id, 'clock_in' => '2026-10-31 18:00:00', 'clock_out' => '2026-10-31 22:00:00', 'total_hours' => 4, 'gross_wage' => 60]);
        $this->postJson('/api/lexware-export/preview', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31'])->assertOk()->assertJsonPath('summary.total_hours', 4)->assertJsonPath('summary.total_wage', 60);
    }
}

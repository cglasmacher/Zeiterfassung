<?php

use Illuminate\Database\Seeder;
use App\Models\{
    Department,
    Employee,
    ShiftType,
    Shift,
    BreakRule
};
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Departments
        $kitchen = Department::create(['name' => 'Küche']);
        $service = Department::create(['name' => 'Service']);

        // Shift Types
        $early = ShiftType::create([
            'name' => 'Frühschicht',
            'default_start' => '06:00:00',
            'default_end' => '14:00:00',
            'default_break_minutes' => 30,
        ]);

        $late = ShiftType::create([
            'name' => 'Spätschicht',
            'default_start' => '14:00:00',
            'default_end' => '22:00:00',
            'default_break_minutes' => 30,
        ]);

        $night = ShiftType::create([
            'name' => 'Nachtschicht',
            'default_start' => '22:00:00',
            'default_end' => '06:00:00',
            'default_break_minutes' => 45,
        ]);

        // Employees
        $emp = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'hourly_rate' => 15.00,
            'rfid_tag' => 'ABCD1234',
        ]);

        $emp->departments()->attach([$kitchen->id]);

        // Break Rules
        BreakRule::insert([
            ['name' => 'Ab 6 Stunden', 'min_hours' => 6, 'break_minutes' => 30, 'active' => true],
            ['name' => 'Ab 9 Stunden', 'min_hours' => 9, 'break_minutes' => 45, 'active' => true],
        ]);

        // Beispielschicht
        Shift::create([
            'employee_id' => $emp->id,
            'department_id' => $kitchen->id,
            'shift_type_id' => $early->id,
            'shift_date' => Carbon::today(),
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'status' => 'planned',
        ]);
    }
}

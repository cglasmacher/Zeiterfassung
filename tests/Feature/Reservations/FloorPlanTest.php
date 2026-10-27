<?php

namespace Tests\Feature\Reservations;

use App\Models\ReservationRoom;
use App\Models\ReservationTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FloorPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        // Keep this module's tests independent of legacy MySQL-only HACCP migrations.
        return ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000004_create_departments_table.php',
            'database/migrations/2026_10_21_100000_create_infosystem_tables.php',
            'database/migrations/2026_10_21_110000_add_workspace_access.php',
            'database/migrations/2026_10_25_220000_create_reservation_floor_plans.php',
            'database/migrations/2026_10_25_230000_create_reservation_book.php',
            'database/migrations/2026_10_25_235000_add_resmio_reservation_sync.php',
            'database/migrations/2026_10_25_235500_add_local_reservation_table_assignment.php',
            'database/migrations/2026_10_26_140000_create_reservation_widget.php',
            'database/migrations/2026_10_26_150000_create_reservation_mail_outbox.php',
            'database/migrations/2026_10_26_160000_add_reservation_guest_actions.php',
        ]];
    }

    private function admin(): User
    {
        return User::factory()->create(['access_role' => 'admin']);
    }

    private function room(): array
    {
        return $this->postJson('/api/reservations/floor-plan/rooms', ['name' => 'Restaurant', 'template' => 'restaurant'])->assertCreated()->json();
    }

    private function payload(array $room): array
    {
        return [
            'name' => $room['name'], 'width' => $room['width'], 'height' => $room['height'], 'revision' => $room['revision'],
            'elements' => $room['elements'],
            'tables' => array_map(fn ($table) => array_intersect_key($table, array_flip(['id', 'name', 'capacity', 'shape', 'x', 'y', 'width', 'height', 'rotation'])), $room['tables']),
        ];
    }

    public function test_only_admins_can_access_and_change_floor_plans(): void
    {
        $this->get('/reservierungen/tischplan')->assertRedirect('/login');
        foreach (['employee', 'operations'] as $role) {
            $this->actingAs(User::factory()->create(['access_role' => $role]));
            $this->get('/reservierungen/tischplan')->assertForbidden();
            $this->getJson('/api/reservations/floor-plan')->assertForbidden();
            $this->postJson('/api/reservations/floor-plan/rooms', ['name' => 'Test'])->assertForbidden();
        }
        $this->actingAs($this->admin());
        $this->get('/reservierungen/tischplan')->assertOk();
    }

    public function test_templates_and_positions_persist_with_stable_ids(): void
    {
        $this->actingAs($this->admin());
        $room = $this->room();
        $this->assertCount(15, $room['tables']);
        $this->assertEquals(30, array_sum(array_column($room['tables'], 'capacity')));
        $bar = $this->postJson('/api/reservations/floor-plan/rooms', ['name' => 'Theke', 'template' => 'bar'])->assertCreated()->json();
        $this->assertEquals(12, array_sum(array_column($bar['tables'], 'capacity')));
        $data = $this->payload($room);
        $id = $data['tables'][0]['id'];
        $data['tables'][0]['x'] = 300;
        $data['tables'][0]['rotation'] = 45;
        $data['elements'][] = ['id' => (string) Str::uuid(), 'type' => 'wall', 'label' => 'Wand', 'x' => 600, 'y' => 400, 'width' => 200, 'height' => 10, 'rotation' => 0];
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertOk()->assertJsonPath('revision', 2);
        $this->assertDatabaseHas('reservation_tables', ['id' => $id, 'x' => 300, 'rotation' => 45]);
        $this->getJson('/api/reservations/floor-plan')->assertOk()->assertJsonCount(2, 'rooms');
    }

    public function test_stale_editor_cannot_overwrite_saved_room(): void
    {
        $this->actingAs($this->admin());
        $room = $this->room();
        $data = $this->payload($room);
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertOk();
        $data['name'] = 'Veralteter Stand';
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertConflict();
        $this->assertDatabaseHas('reservation_rooms', ['id' => $room['id'], 'name' => 'Restaurant', 'revision' => 2]);
    }

    public function test_invalid_names_geometry_and_foreign_table_ids_are_rejected_atomically(): void
    {
        $this->actingAs($this->admin());
        $room = $this->room();
        $original = $this->payload($room);
        $data = $original;
        $data['tables'][1]['name'] = ' '.$data['tables'][0]['name'].' ';
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertUnprocessable();
        $data = $original;
        $data['tables'][0]['x'] = 1;
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertUnprocessable();
        $data = $original;
        $data['width'] = 400;
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertUnprocessable();
        $other = $this->room();
        $data = $original;
        $data['tables'][0]['id'] = $other['tables'][0]['id'];
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertUnprocessable();
        $data = $original;
        $data['tables'][0]['room_id'] = $other['id'];
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertUnprocessable();
        $this->assertEquals(1, ReservationRoom::findOrFail($room['id'])->revision);
        $this->assertEquals(15, ReservationTable::where('room_id', $room['id'])->count());
    }

    public function test_removal_preserves_identity_and_cannot_resurrect_deleted_table(): void
    {
        $this->actingAs($this->admin());
        $room = $this->room();
        $data = $this->payload($room);
        $removed = array_pop($data['tables']);
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertOk()->assertJsonCount(14, 'tables');
        $this->assertSoftDeleted('reservation_tables', ['id' => $removed['id']]);
        $data['revision'] = 2;
        $data['tables'][] = $removed;
        $this->putJson('/api/reservations/floor-plan/rooms/'.$room['id'], $data)->assertUnprocessable();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ReservationRoom;
use App\Models\ReservationTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationFloorPlanController extends Controller
{
    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->access_role === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->authorizeEditor($request);

        return response()->json(['rooms' => ReservationRoom::with('tables')->orderBy('created_at')->orderBy('id')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:80', 'not_regex:/^\s*$/u'], 'template' => ['sometimes', Rule::in(['empty', 'restaurant', 'bar'])]]);

        return DB::transaction(function () use ($data) {
            app(\App\Services\Reservations\ReservationService::class)->lock();
            $room = ReservationRoom::create(['id' => (string) Str::uuid(), 'name' => trim($data['name']), 'width' => 1200, 'height' => 800, 'elements' => []]);
            $template = $data['template'] ?? 'empty';
            $names = match ($template) {
                'restaurant' => [1, 2, 3, 4, 5, 6, 7, 8, 11, 12, 13, 14, 15, 16, 17],
                'bar' => [21, 20],
                default => [],
            };
            foreach ($names as $index => $name) {
                $room->tables()->create([
                    'id' => (string) Str::uuid(), 'name' => (string) $name,
                    'capacity' => $template === 'bar' ? ($name === 21 ? 4 : 8) : 2,
                    'shape' => 'rectangle', 'x' => 140 + ($index % 5) * 220,
                    'y' => 140 + intdiv($index, 5) * 220,
                    'width' => $template === 'bar' ? ($name === 21 ? 120 : 200) : 80,
                    'height' => 70, 'rotation' => 0,
                ]);
            }

            return response()->json($room->fresh()->load('tables'), 201);
        });
    }

    public function update(Request $request, ReservationRoom $room)
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:80', 'not_regex:/^\s*$/u'],
            'width' => ['required', 'integer', 'between:400,4000'],
            'height' => ['required', 'integer', 'between:300,3000'],
            'tables' => ['present', 'array', 'max:200'],
            'tables.*' => ['array:id,name,capacity,shape,x,y,width,height,rotation'],
            'tables.*.id' => ['required', 'uuid', 'distinct'],
            'tables.*.name' => ['required', 'string', 'max:40', 'not_regex:/^\s*$/u'],
            'tables.*.capacity' => ['required', 'integer', 'between:1,100'],
            'tables.*.shape' => ['required', Rule::in(['rectangle', 'round', 'oval'])],
            'tables.*.x' => ['required', 'integer', 'min:0'],
            'tables.*.y' => ['required', 'integer', 'min:0'],
            'tables.*.width' => ['required', 'integer', 'between:30,600'],
            'tables.*.height' => ['required', 'integer', 'between:30,600'],
            'tables.*.rotation' => ['required', 'integer', 'between:0,359'],
            'elements' => ['present', 'array', 'max:300'],
            'elements.*' => ['array:id,type,label,x,y,width,height,rotation'],
            'elements.*.id' => ['required', 'uuid', 'distinct'],
            'elements.*.type' => ['required', Rule::in(['wall', 'door', 'bar', 'text', 'plant'])],
            'elements.*.label' => ['nullable', 'string', 'max:100'],
            'elements.*.x' => ['required', 'integer', 'min:0'],
            'elements.*.y' => ['required', 'integer', 'min:0'],
            'elements.*.width' => ['required', 'integer', 'between:10,2000'],
            'elements.*.height' => ['required', 'integer', 'between:10,2000'],
            'elements.*.rotation' => ['required', 'integer', 'between:0,359'],
        ]);

        return DB::transaction(function () use ($room, $data) {
            app(\App\Services\Reservations\ReservationService::class)->lock();
            $locked = ReservationRoom::whereKey($room->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->revision !== (int) $data['revision'], 409, 'Der Raum wurde zwischenzeitlich geändert. Bitte Änderungen sichern und den gespeicherten Stand neu laden.');
            $names = [];
            $ids = [];
            foreach ($data['tables'] as $index => &$table) {
                $table['name'] = trim($table['name']);
                $name = mb_strtolower($table['name']);
                if (in_array($name, $names, true)) {
                    throw ValidationException::withMessages(["tables.$index.name" => 'Tischnamen müssen innerhalb eines Raums eindeutig sein.']);
                }
                $names[] = $name;
                $ids[] = $table['id'];
                $existing = ReservationTable::withTrashed()->find($table['id']);
                if ($existing && ($existing->room_id !== $locked->id || $existing->trashed())) {
                    throw ValidationException::withMessages(["tables.$index.id" => 'Diese Tischkennung kann nicht verwendet werden.']);
                }
            }
            unset($table);
            foreach (array_merge($data['tables'], $data['elements']) as $object) {
                $angle = deg2rad($object['rotation']);
                $extentX = abs(cos($angle)) * $object['width'] / 2 + abs(sin($angle)) * $object['height'] / 2;
                $extentY = abs(sin($angle)) * $object['width'] / 2 + abs(cos($angle)) * $object['height'] / 2;
                if ($object['x'] - $extentX < -0.01 || $object['y'] - $extentY < -0.01 || $object['x'] + $extentX > $data['width'] + 0.01 || $object['y'] + $extentY > $data['height'] + 0.01) {
                    throw ValidationException::withMessages(['tables' => 'Alle Objekte müssen vollständig innerhalb des Raums liegen.']);
                }
            }
            $objectIds = array_merge($ids, array_column($data['elements'], 'id'));
            if (count($objectIds) !== count(array_unique($objectIds))) {
                throw ValidationException::withMessages(['elements' => 'Objektkennungen müssen eindeutig sein.']);
            }
            $currentTables = $locked->tables()->get();
            $incoming = collect($data['tables'])->keyBy('id');
            $protectedIds = $currentTables->filter(fn ($table) => ! $incoming->has($table->id) || $incoming[$table->id]['capacity'] < $table->capacity)->pluck('id')->all();
            if ($protectedIds) {
                $used = \App\Models\ReservationBooking::whereIn('status', \App\Models\ReservationBooking::ACTIVE)
                    ->where(fn ($q) => $q->where('ends_at', '>', \App\Services\Reservations\ReservationService::stamp(now()))->orWhere('status', 'seated'))
                    ->whereHas('tables', fn ($q) => $q->whereIn('reservation_tables.id', $protectedIds))->exists();
                if ($used) {
                    throw ValidationException::withMessages(['tables' => 'Mindestens ein betroffener Tisch ist für eine aktuelle oder zukünftige Buchung eingeplant. Bitte zuerst die Buchung umsetzen.']);
                }
            }
            $removed = $currentTables->pluck('id')->diff($ids)->all();
            if ($removed && \App\Models\ReservationCombination::all()->contains(fn ($c) => count(array_intersect($removed, $c->table_ids)) > 0)) {
                throw ValidationException::withMessages(['tables' => 'Ein zu entfernender Tisch gehört zu einer Tischkombination. Bitte zuerst die Kombination in der Reservierungsverwaltung entfernen.']);
            }
            // Soft deletion preserves table identity for future reservation references.
            $locked->tables()->whereNotIn('id', $ids)->delete();
            foreach ($data['tables'] as $table) {
                $locked->tables()->updateOrCreate(['id' => $table['id']], $table);
            }
            $locked->update(['name' => trim($data['name']), 'width' => $data['width'], 'height' => $data['height'], 'elements' => $data['elements'], 'revision' => $locked->revision + 1]);

            return response()->json($locked->fresh()->load('tables'));
        });
    }
}

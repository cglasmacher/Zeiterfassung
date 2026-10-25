<?php

namespace App\Http\Controllers;

use App\Models\ReservationBlock;
use App\Models\ReservationBooking;
use App\Models\ReservationCombination;
use App\Models\ReservationRoom;
use App\Models\ReservationTable;
use App\Services\Reservations\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationBookController extends Controller
{
    public function __construct(private ReservationService $service) {}

    private function access(Request $request, bool $admin = false): void
    {
        abort_unless(in_array($request->user()?->access_role, $admin ? ['admin'] : ['admin', 'operations'], true), 403);
    }

    public function index(Request $request)
    {
        $this->access($request);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $start = $this->service->localTime($data['date'], '00:00');
        $end = $start->addDay();

        return response()->json([
            'date' => $data['date'], 'server_now' => CarbonImmutable::now('Europe/Berlin')->toIso8601String(),
            'can_manage' => $request->user()->access_role === 'admin',
            'resmio' => $this->resmioState(),
            'settings' => $this->service->settings(),
            'rooms' => ReservationRoom::with('tables')->orderBy('created_at')->orderBy('id')->get(),
            'combinations' => ReservationCombination::orderBy('name')->get(),
            'bookings' => ReservationBooking::with('tables')->where(function ($q) use ($start, $end) {
                $q->where(fn ($q) => $q->where('starts_at', '>=', ReservationService::stamp($start))->where('starts_at', '<', ReservationService::stamp($end)))
                    ->orWhere(fn ($q) => $q->whereIn('status', ReservationBooking::ACTIVE)->where('starts_at', '<', ReservationService::stamp($end))->where('ends_at', '>', ReservationService::stamp($start)));
            })->orderBy('starts_at')->orderBy('id')->get()->map(fn ($booking) => $this->service->withCurrentIssues($booking)),
            'blocks' => ReservationBlock::where('starts_at', '<', ReservationService::stamp($end))->where('ends_at', '>', ReservationService::stamp($start))->orderBy('starts_at')->get(),
        ]);
    }

    private function bookingData(Request $request, bool $update = false): array
    {
        return $request->validate([
            'kind' => [$update ? 'sometimes' : 'required', Rule::in(['reservation', 'walkin', 'waitlist'])],
            'date' => [$update ? 'sometimes' : 'required_unless:kind,walkin', 'date_format:Y-m-d', 'required_with:time'],
            'time' => [$update ? 'sometimes' : 'required_unless:kind,walkin', 'date_format:H:i', 'required_with:date'],
            'party_size' => [$update ? 'sometimes' : 'required', 'integer', 'between:1,100'],
            'duration_minutes' => ['sometimes', 'integer', 'between:15,720'],
            'guest_name' => ['nullable', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email:rfc', 'max:200'], 'notes' => ['nullable', 'string', 'max:2000'],
            'source' => ['sometimes', Rule::in(['phone', 'email', 'manual', 'walkin'])],
            'status' => ['sometimes', Rule::in(ReservationBooking::STATUSES)],
            'table_ids' => ['sometimes', 'array', 'max:20'], 'table_ids.*' => ['uuid', 'distinct', Rule::exists('reservation_tables', 'id')],
            'room_id' => ['nullable', 'uuid', Rule::exists('reservation_rooms', 'id')],
            'version' => [$update ? 'required' : 'sometimes', 'integer', 'min:1'],
            'reset_resmio_status' => ['sometimes', 'boolean'],
            'reset_resmio_tables' => ['sometimes', 'boolean'],
        ]);
    }

    public function store(Request $request)
    {
        $this->access($request);
        $data = $this->bookingData($request);
        // Creation follows the actual workflow; statuses are changed on an existing booking.
        unset($data['status']);

        return response()->json($this->service->write($data, $request->user()->id), 201);
    }

    public function update(Request $request, ReservationBooking $booking)
    {
        $this->access($request);

        return response()->json($this->service->write($this->bookingData($request, true), $request->user()->id, $booking));
    }

    public function promote(Request $request, ReservationBooking $booking)
    {
        $this->access($request);

        return response()->json($this->service->write($this->bookingData($request, true), $request->user()->id, $booking, true));
    }

    public function availability(Request $request)
    {
        $this->access($request);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'between:1,100'], 'duration_minutes' => ['required', 'integer', 'between:15,720'],
            'room_id' => ['nullable', 'uuid', Rule::exists('reservation_rooms', 'id')],
            'except' => ['nullable', 'uuid', Rule::exists('reservation_bookings', 'id')],
            'kind' => ['required', Rule::in(['reservation', 'walkin', 'waitlist'])],
        ]);
        $start = $data['kind'] === 'walkin' ? CarbonImmutable::now('Europe/Berlin')->startOfMinute() : $this->service->localTime($data['date'], $data['time']);
        if ($data['kind'] === 'walkin' && ! empty($data['except'])) {
            $existing = ReservationBooking::find($data['except']);
            if ($existing?->kind === 'walkin') {
                $start = $existing->starts_at;
            }
        }
        if ($data['kind'] === 'reservation' && (empty($data['except']) || ! ReservationBooking::find($data['except'])?->starts_at->eq($start))) {
            $this->service->checkHours($start);
        }

        return response()->json(['options' => $this->service->candidates($start, $start->addMinutes((int) $data['duration_minutes']), $data['party_size'], $data['room_id'] ?? null, $data['except'] ?? null), 'tables' => $this->service->tableInventory($start, $start->addMinutes((int) $data['duration_minutes']), $data['except'] ?? null)]);
    }

    public function tableOptions(Request $request, ReservationBooking $booking)
    {
        $this->access($request);

        return response()->json(['options' => $this->service->candidates($booking->starts_at, $booking->ends_at, $booking->party_size, null, $booking->id, $booking->source === 'resmio'), 'tables' => $this->service->tableInventory($booking->starts_at, $booking->ends_at, $booking->id, $booking->source === 'resmio')]);
    }

    public function events(Request $request, ReservationBooking $booking)
    {
        $this->access($request);

        return response()->json(DB::table('reservation_events')->leftJoin('users', 'users.id', '=', 'reservation_events.user_id')->where('booking_id', $booking->id)->select('reservation_events.id', 'reservation_events.action', 'reservation_events.created_at', 'users.name as user_name')->orderByDesc('reservation_events.id')->limit(30)->get()->map(function ($event) {
            $event->created_at = CarbonImmutable::parse($event->created_at, config('app.timezone'))->toIso8601String();

            return $event;
        }));
    }

    public function settings(Request $request)
    {
        $this->access($request, true);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'], 'configuration' => ['required', 'array:weekdays,opens_at,closes_at,slot_minutes,default_duration,durations,require_name,require_phone'],
            'configuration.weekdays' => ['present', 'array', 'max:7'], 'configuration.weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'configuration.opens_at' => ['required', 'date_format:H:i'], 'configuration.closes_at' => ['required', 'date_format:H:i', 'after:configuration.opens_at'],
            'configuration.slot_minutes' => ['required', Rule::in([15, 30, 60])], 'configuration.default_duration' => ['required', 'integer', 'between:15,720'],
            'configuration.durations' => ['present', 'array', 'max:10'], 'configuration.durations.*' => ['array:from,minutes'],
            'configuration.durations.*.from' => ['required', 'integer', 'between:1,100', 'distinct'], 'configuration.durations.*.minutes' => ['required', 'integer', 'between:15,720'],
            'configuration.require_name' => ['required', 'boolean'], 'configuration.require_phone' => ['required', 'boolean'],
        ]);

        return DB::transaction(function () use ($data) {
            $row = $this->service->lock();
            abort_if((int) $row->revision !== (int) $data['revision'], 409, 'Die Einstellungen wurden bereits geändert. Bitte neu laden.');
            $config = $data['configuration'];
            $config['weekdays'] = array_map('intval', $config['weekdays']);
            foreach (['slot_minutes', 'default_duration'] as $field) {
                $config[$field] = (int) $config[$field];
            }
            $config['durations'] = array_map(fn ($rule) => ['from' => (int) $rule['from'], 'minutes' => (int) $rule['minutes']], $config['durations']);
            foreach (['require_name', 'require_phone'] as $field) {
                $config[$field] = (bool) $config[$field];
            }
            DB::table('reservation_settings')->where('id', 1)->update(['configuration' => json_encode($config), 'revision' => $row->revision + 1]);

            return response()->json($this->service->settings());
        });
    }

    private function combinationValues(array $data): array
    {
        $ids = $data['table_ids'];
        sort($ids);
        $tables = ReservationTable::whereIn('id', $ids)->get();
        if ($tables->count() !== count($ids) || $tables->pluck('room_id')->unique()->count() !== 1 || $tables->first()->room_id !== $data['room_id']) {
            throw ValidationException::withMessages(['table_ids' => 'Alle Tische müssen aktiv sein und zum gewählten Raum gehören.']);
        }
        $duplicate = ReservationCombination::where('room_id', $data['room_id'])->get()->contains(function ($c) use ($ids) {
            $other = $c->table_ids;
            sort($other);

            return $other === $ids;
        });
        if ($duplicate) {
            throw ValidationException::withMessages(['table_ids' => 'Diese Tischkombination ist bereits hinterlegt.']);
        }

        return ['id' => (string) Str::uuid(), 'name' => trim($data['name']), 'room_id' => $data['room_id'], 'table_ids' => $ids, 'capacity' => $data['capacity']];
    }

    public function storeCombination(Request $request)
    {
        $this->access($request, true);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/u'], 'room_id' => ['required', 'uuid', Rule::exists('reservation_rooms', 'id')], 'capacity' => ['required', 'integer', 'between:1,100'], 'table_ids' => ['required', 'array', 'min:2', 'max:20'], 'table_ids.*' => ['required', 'uuid', 'distinct']]);

        return DB::transaction(function () use ($data) {
            $this->service->lock();

            return response()->json(ReservationCombination::create($this->combinationValues($data)), 201);
        });
    }

    public function deleteCombination(Request $request, ReservationCombination $combination)
    {
        $this->access($request, true);

        return DB::transaction(function () use ($combination) {
            $this->service->lock();
            $used = ReservationBooking::with('tables')->whereIn('status', ReservationBooking::ACTIVE)
                ->where('ends_at', '>', ReservationService::stamp(now()))
                ->whereHas('tables', fn ($q) => $q->whereIn('reservation_tables.id', $combination->table_ids))->get()
                ->contains(function ($booking) use ($combination) {
                    $ids = $booking->tables->pluck('id')->all();
                    sort($ids);
                    $other = $combination->table_ids;
                    sort($other);

                    return $ids === $other;
                });
            abort_if($used, 409, 'Diese Kombination wird für eine aktuelle oder zukünftige Buchung verwendet. Bitte zuerst die Buchung umsetzen.');
            $combination->delete();

            return response()->noContent();
        });
    }

    public function importCombinations(Request $request)
    {
        $this->access($request, true);
        $data = $request->validate(['room_id' => ['required', 'uuid', Rule::exists('reservation_rooms', 'id')]]);

        return DB::transaction(function () use ($data) {
            $this->service->lock();
            $tables = ReservationTable::where('room_id', $data['room_id'])->get()->keyBy('name');
            $definitions = [[[1, 3], 5], [[1, 3, 4], 7], [[6, 7], 5], [[4, 6, 7], 7], [[11, 12, 13], 8], [[17, 15, 16], 7], [[11, 12], 5], [[17, 16], 5], [[13, 14], 4], [[11, 12, 13, 14], 9], [[1, 2, 3], 7]];
            $created = 0;
            $skipped = [];
            foreach ($definitions as [$names, $capacity]) {
                $name = implode(' + ', $names);
                if (array_diff($names, $tables->keys()->all())) {
                    $skipped[] = $name;

                    continue;
                }
                $ids = array_map(fn ($n) => $tables[$n]->id, $names);
                try {
                    $values = $this->combinationValues(['room_id' => $data['room_id'], 'table_ids' => $ids, 'capacity' => $capacity, 'name' => $name]);
                } catch (ValidationException $e) {
                    $skipped[] = $name;

                    continue;
                }
                ReservationCombination::create($values);
                $created++;
            }

            return response()->json(['created' => $created, 'skipped' => $skipped]);
        });
    }

    public function storeBlock(Request $request)
    {
        $this->access($request);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/u'],
            'start_date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'], 'end_time' => ['required', 'date_format:H:i'],
            'room_id' => ['nullable', 'uuid', Rule::exists('reservation_rooms', 'id')],
            'table_ids' => ['sometimes', 'array', 'max:200'], 'table_ids.*' => ['uuid', 'distinct'],
        ]);

        return DB::transaction(function () use ($data) {
            $this->service->lock();
            $start = $this->service->localTime($data['start_date'], $data['start_time']);
            $end = $this->service->localTime($data['end_date'], $data['end_time']);
            if ($end <= $start) {
                throw ValidationException::withMessages(['end_time' => 'Das Ende muss nach dem Beginn liegen.']);
            }
            $ids = $data['table_ids'] ?? [];
            $tables = ReservationTable::query()->when($ids, fn ($q) => $q->whereIn('id', $ids))->when($data['room_id'] ?? null, fn ($q, $room) => $q->where('room_id', $room))->get();
            if ($ids && count($ids) !== $tables->count()) {
                throw ValidationException::withMessages(['table_ids' => 'Ungültige Tischauswahl für diesen Raum.']);
            }
            $affected = $this->service->occupied($start, $end)->whereHas('tables', fn ($q) => $q->whereIn('reservation_tables.id', $tables->pluck('id')))->count();
            abort_if($affected > 0, 409, "Die Sperre betrifft $affected bestehende Buchung(en). Bitte zuerst umsetzen oder stornieren.");

            return response()->json(ReservationBlock::create(['id' => (string) Str::uuid(), 'reason' => trim($data['reason']), 'room_id' => $data['room_id'] ?? null, 'table_ids' => $ids ?: null, 'starts_at' => $start, 'ends_at' => $end]), 201);
        });
    }

    public function deleteBlock(Request $request, ReservationBlock $block)
    {
        $this->access($request);

        return DB::transaction(function () use ($block) {
            $this->service->lock();
            $block->delete();

            return response()->noContent();
        });
    }

    public function resmioState(): array
    {
        $s = \App\Models\InfoIntegration::settings();

        return ['enabled' => $s->enabled, 'facility' => $s->facility, 'last_success_at' => $s->last_success_at?->toIso8601String(), 'last_error' => $s->last_error];
    }

    public function syncResmio(Request $request, \App\Services\Info\ResmioSync $sync)
    {
        $this->access($request, true);
        try {
            return response()->json(['count' => $sync->run()]);
        } catch (\Throwable $e) {
            return response()->json(['message' => \App\Exceptions\ResmioSyncException::messageFor($e)], 502);
        }
    }

    public function resmioResources(Request $request)
    {
        $this->access($request, true);

        return response()->json(DB::table('reservation_resmio_resources')->where('facility', \App\Models\InfoIntegration::settings()->facility)->orderBy('name')->get());
    }

    public function mapResmioResource(Request $request, int $resource)
    {
        $this->access($request, true);
        $data = $request->validate(['table_id' => ['present', 'nullable', 'uuid', Rule::exists('reservation_tables', 'id')->whereNull('deleted_at')], 'revision' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($resource, $data) {
            $this->service->lock();
            $row = DB::table('reservation_resmio_resources')->where('id', $resource)->where('facility', \App\Models\InfoIntegration::settings()->facility)->firstOrFail();
            abort_if((int) $row->revision !== (int) $data['revision'], 409, 'Diese Tischzuordnung wurde bereits geändert. Bitte die Zuordnungen neu laden.');
            DB::table('reservation_resmio_resources')->where('id', $row->id)->update(['table_id' => $data['table_id'] ?? null, 'mapping_mode' => 'manual', 'revision' => $row->revision + 1]);
            $affected = ReservationBooking::where('resmio_facility', $row->facility)->whereIn('status', array_merge(ReservationBooking::ACTIVE, ['waiting']))->get();
            foreach ($affected as $booking) {
                if (! in_array($row->external_id, $booking->resmio_data['facility_resources'] ?? [], true)) {
                    continue;
                }
                $missing = $booking->resmio_missing;
                app(\App\Services\Reservations\ResmioBookingImport::class)->one($row->facility, $booking->resmio_data);
                if ($missing) {
                    $booking->fresh()->update(['resmio_missing' => true]);
                }
            }

            return response()->json(['saved' => true]);
        });
    }
}

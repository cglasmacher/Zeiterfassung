<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $typeNames = ['Tiefkühlschrank', 'Tiefkühltruhe', 'Kühlhaus', 'Kühlschrank', 'Kühlunterbau'];
        $types = DB::table('haccp_device_types')->whereIn('name', $typeNames)->get()->keyBy('name');

        foreach ($typeNames as $name) {
            $type = $types->get($name);
            if (!$type) {
                continue;
            }

            DB::table('haccp_device_types')->where('id', $type->id)->update([
                'default_frequency' => 'daily',
                'default_interval_value' => 1,
                'updated_at' => $now,
            ]);

            $configuration = [
                'integer_only' => true,
                'inherit_device_limits' => true,
            ];

            $existing = DB::table('haccp_templates')
                ->where('name', 'Temperaturkontrolle '.$name)
                ->where('device_type_id', $type->id)
                ->whereNull('device_id')
                ->orderByDesc('version')
                ->first();

            $data = [
                'category' => 'Temperaturkontrolle',
                'criticality' => 'elevated',
                'haccp_role_id' => null,
                'device_id' => null,
                'device_type_id' => $type->id,
                'result_type' => 'temperature',
                'configuration' => json_encode($configuration),
                'frequency' => 'daily',
                'schedule' => json_encode(['times' => ['10:00'], 'window_before_minutes' => 120]),
                'requires_corrective_action' => true,
                'active' => true,
                'updated_at' => $now,
            ];

            if ($existing && !$existing->locked_at) {
                DB::table('haccp_templates')->where('id', $existing->id)->update($data);
            } elseif (!$existing) {
                DB::table('haccp_templates')->insert($data + [
                    'name' => 'Temperaturkontrolle '.$name,
                    'version' => 1,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $typeIds = DB::table('haccp_device_types')->whereIn('name', [
            'Tiefkühlschrank', 'Tiefkühltruhe', 'Kühlhaus', 'Kühlschrank', 'Kühlunterbau',
        ])->pluck('id');

        DB::table('haccp_templates')
            ->where('category', 'Temperaturkontrolle')
            ->whereIn('device_type_id', $typeIds)
            ->whereNull('device_id')
            ->whereIn('name', [
                'Temperaturkontrolle Tiefkühlschrank',
                'Temperaturkontrolle Tiefkühltruhe',
                'Temperaturkontrolle Kühlhaus',
                'Temperaturkontrolle Kühlschrank',
                'Temperaturkontrolle Kühlunterbau',
            ])->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $types = [
            ['name' => 'Tiefkühlschrank', 'unit' => '°C', 'min' => -23, 'max' => -18],
            ['name' => 'Tiefkühltruhe', 'unit' => '°C', 'min' => -23, 'max' => -18],
            ['name' => 'Kühlhaus', 'unit' => '°C', 'min' => 2, 'max' => 7],
            ['name' => 'Kühlschrank', 'unit' => '°C', 'min' => 2, 'max' => 7],
            ['name' => 'Kühlunterbau', 'unit' => '°C', 'min' => 1, 'max' => 4],
        ];

        foreach ($types as $type) {
            DB::table('haccp_device_types')->updateOrInsert(
                ['name' => $type['name']],
                [
                    'unit' => $type['unit'],
                    'default_min_value' => $type['min'],
                    'default_max_value' => $type['max'],
                    'default_frequency' => 'daily',
                    'default_interval_value' => 1,
                    'active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $typeIds = DB::table('haccp_device_types')->whereIn('name', collect($types)->pluck('name'))->pluck('id', 'name');

        $devices = [
            ['TK 1 Keller', 'Tiefkühlschrank', 'Küchenkeller', 'Liebherr', -23, -18],
            ['TK 2 Keller', 'Tiefkühlschrank', 'Küchenkeller', 'Liebherr', -23, -18],
            ['TK Truhe 1', 'Tiefkühltruhe', 'Küchenkeller', 'Liebherr', -23, -18],
            ['TK Truhe 2', 'Tiefkühltruhe', 'Küchenkeller', 'Liebherr', -23, -18],
            ['TK 3 Küche', 'Tiefkühlschrank', 'Küche', 'Liebherr', -23, -18],
            ['TK 4 Durchgang', 'Tiefkühlschrank', 'Durchgang', 'No Name', -23, -18],
            ['Kühlhaus', 'Kühlhaus', 'Küchenkeller', 'Daikin', 2, 7],
            ['Kühlung Frischware', 'Kühlschrank', 'Durchgang', 'GGM Gastro', 1, 4],
            ['Unterbau klein', 'Kühlunterbau', 'Küche', 'ecofrost', 1, 4],
            ['Unterbau groß', 'Kühlunterbau', 'Küche', 'ecofrost', 1, 4],
            ['Kuchenkühlung Vorrat', 'Kühlschrank', 'Durchgang', 'Metro', 2, 7],
            ['Kuchenkühlung Gastraum', 'Kühlschrank', 'Gastraum', 'Bartscher', 2, 7],
            ['Getränkekühler 1', 'Kühlschrank', 'Durchgang', 'Liebherr', 2, 7],
            ['Getränkekühler 2', 'Kühlschrank', 'Durchgang', 'Nordcap', 2, 7],
        ];

        foreach ($devices as [$name, $type, $location, $manufacturer, $min, $max]) {
            $existing = DB::table('haccp_devices')->where('name', $name)->first();
            $data = [
                'device_type_id' => $typeIds[$type], 'type' => $type, 'location' => $location,
                'manufacturer' => $manufacturer, 'min_value' => $min, 'max_value' => $max,
                'unit' => '°C', 'active' => true, 'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('haccp_devices')->where('id', $existing->id)->update($data);
            } else {
                DB::table('haccp_devices')->insert($data + [
                    'name' => $name, 'qr_token' => Str::random(40), 'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('haccp_devices')->whereIn('name', [
            'TK 1 Keller', 'TK 2 Keller', 'TK Truhe 1', 'TK Truhe 2', 'TK 3 Küche', 'TK 4 Durchgang',
            'Kühlhaus', 'Kühlung Frischware', 'Unterbau klein', 'Unterbau groß', 'Kuchenkühlung Vorrat',
            'Kuchenkühlung Gastraum', 'Getränkekühler 1', 'Getränkekühler 2',
        ])->delete();
    }
};

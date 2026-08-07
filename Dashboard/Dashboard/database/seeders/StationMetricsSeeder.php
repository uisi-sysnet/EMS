<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class StationMetricsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $stations = [
            ['id' => 'STA001', 'name' => 'Alpha',  'lat' => 34.0522, 'lon' => -118.2437, 'elev' => 89.0],
            ['id' => 'STA002', 'name' => 'Beta',   'lat' => 34.0622, 'lon' => -118.2537, 'elev' => 120.5],
            ['id' => 'STA003', 'name' => 'Gamma',  'lat' => 34.0422, 'lon' => -118.2337, 'elev' => 55.2],
            ['id' => 'STA004', 'name' => 'Delta',  'lat' => 34.0722, 'lon' => -118.2637, 'elev' => 200.0],
            ['id' => 'STA005', 'name' => 'Epsilon','lat' => 34.0322, 'lon' => -118.2137, 'elev' => 30.5],
            ['id' => 'STA006', 'name' => 'Zeta',   'lat' => 34.0922, 'lon' => -118.2737, 'elev' => 150.0],
            ['id' => 'STA007', 'name' => 'Eta',    'lat' => 34.0222, 'lon' => -118.2237, 'elev' => 70.0],
            ['id' => 'STA008', 'name' => 'Theta',  'lat' => 34.0822, 'lon' => -118.2837, 'elev' => 180.0],
            ['id' => 'STA009', 'name' => 'Iota',   'lat' => 34.0122, 'lon' => -118.2037, 'elev' => 45.0],
            ['id' => 'STA010', 'name' => 'Kappa',  'lat' => 34.1022, 'lon' => -118.2937, 'elev' => 250.0],
        ];

        $rowsPerStation = 100;
        $connection = DB::connection('seismic');
        $records = [];

        foreach ($stations as $station) {
            for ($i = 0; $i < $rowsPerStation; $i++) {
                $time = now()->subMinutes(rand(0, 7 * 24 * 60));

                // Acceleration (m/s²)
                $accX = $faker->randomFloat(4, -0.01, 0.01);
                $accY = $faker->randomFloat(4, -0.01, 0.01);
                $accZ = 9.81 + $faker->randomFloat(4, -0.05, 0.05);

                // Velocity (m/s)
                $velX = $faker->randomFloat(6, -0.001, 0.001);
                $velY = $faker->randomFloat(6, -0.001, 0.001);
                $velZ = $faker->randomFloat(6, -0.0005, 0.0005);

                // Displacement (m)
                $dispX = $faker->randomFloat(8, -0.00001, 0.00001);
                $dispY = $faker->randomFloat(8, -0.00001, 0.00001);
                $dispZ = $faker->randomFloat(8, -0.000005, 0.000005);

                // PGA (m/s²)
                $pga = $faker->randomFloat(3, 0.01, 0.10);

                // PEIS – must be integer
                $peis = (int) round($faker->randomFloat(1, 2.0, 6.0));

                // Elevation – round to integer (real column accepts it, but we keep it safe)
                $elevation = (int) round($station['elev']);

                // Source – exactly 10 characters: 'src_' + 6-char station ID
                $source = 'src_' . $station['id']; // e.g., src_STA001

                $records[] = [
                    'time'          => $time,
                    'station_id'    => $station['id'],
                    'station_name'  => $station['name'],
                    'latitude'      => $station['lat'],
                    'longitude'     => $station['lon'],
                    'elevation_m'   => $elevation,
                    'acc_x'         => $accX,
                    'acc_y'         => $accY,
                    'acc_z'         => $accZ,
                    'vel_x'         => $velX,
                    'vel_y'         => $velY,
                    'vel_z'         => $velZ,
                    'disp_x'        => $dispX,
                    'disp_y'        => $dispY,
                    'disp_z'        => $dispZ,
                    'pga'           => $pga,
                    'peis'          => $peis,
                    'source'        => $source,
                ];
            }
        }

        $connection->table('station_metrics')->insert($records);
        $this->command->info('1000 station metrics seeded successfully!');
    }
}
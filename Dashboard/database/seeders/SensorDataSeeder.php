<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class SensorDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $connection = DB::connection('aq');

        $connection->table('sensor_data')->truncate();

        $stations = $connection->table('stations')->get();

        $rows = [];

        for ($i = 1; $i <= 1000; $i++) {

            $station = $stations->random();

            $rows[] = [

                'station_mn' => $station->station_mn,
                'ip_address' => $station->lead_ip,

                'data_time' => $faker->dateTimeBetween('-30 days', 'now'),

                'pm25' => $faker->randomFloat(2, 0, 150),
                'pm10' => $faker->randomFloat(2, 0, 250),
                'tsp' => $faker->randomFloat(2, 0, 350),

                'ozone' => $faker->randomFloat(3, 0, 0.25),
                'carbon_monoxide' => $faker->randomFloat(3, 0, 20),
                'sulfur_dioxide' => $faker->randomFloat(3, 0, 5),
                'nitrogen_dioxide' => $faker->randomFloat(3, 0, 10),

                'temperature' => $faker->randomFloat(1, 22, 38),
                'humidity' => $faker->randomFloat(1, 40, 95),
                'rain' => $faker->randomFloat(2, 0, 50),

                'wind_speed' => $faker->randomFloat(2, 0, 40),
                'wind_direction' => $faker->numberBetween(0, 360),
                'air_pressure' => $faker->randomFloat(2, 980, 1035),

                'noise' => $faker->randomFloat(2, 30, 120),

                'lead' => $faker->randomFloat(4, 0, 0.5),
                'lead_temperature' => $faker->randomFloat(1, 20, 40),

                'created_at' => now(),
            ];

            // Insert every 200 rows
            if (count($rows) == 200) {
                $connection->table('sensor_data')->insert($rows);
                $rows = [];
            }
        }

        // Insert remaining rows
        if (!empty($rows)) {
            $connection->table('sensor_data')->insert($rows);
        }

        $this->command->info('15 stations created.');
        $this->command->info('1000 sensor_data records created.');
    }
}
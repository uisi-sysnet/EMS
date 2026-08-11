<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SensorDataSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection('aq');
        
        // Get all stations
        $stations = $connection->table('stations')->where('deleted', false)->get();
        
        if ($stations->isEmpty()) {
            $this->command->warn('No stations found. Please run StationsSeeder first.');
            return;
        }

        $this->command->info('Generating random sensor data for ' . $stations->count() . ' stations...');

        $totalRecords = 0;
        $bar = $this->command->getOutput()->createProgressBar($stations->count());
        $bar->start();

        foreach ($stations as $station) {
            $records = $this->generateRandomData($connection, $station);
            $totalRecords += $records;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Generated {$totalRecords} sensor data records successfully!");
    }

    private function generateRandomData($connection, $station): int
    {
        // Generate 3 days of data with random intervals
        $startDate = Carbon::now()->subDays(3);
        $endDate = Carbon::now();
        $currentTime = clone $startDate;
        
        $dataBatch = [];
        $batchSize = 500;
        $totalRecords = 0;

        while ($currentTime <= $endDate) {
            $intervalMinutes = rand(3, 15);
            
            $data = [
                'station_mn' => $station->station_mn,
                'ip_address' => $station->lead_ip,
                'data_time' => $currentTime->format('Y-m-d H:i:s'),
                'pm25' => round(rand(0, 1000) / 10, 1),
                'pm10' => round(rand(0, 2000) / 10, 1),
                'tsp' => round(rand(0, 4000) / 10, 1),
                'ozone' => round(rand(0, 200) / 1000, 3),
                'carbon_monoxide' => round(rand(0, 1000) / 100, 2),
                'sulfur_dioxide' => round(rand(0, 100) / 1000, 3),
                'nitrogen_dioxide' => round(rand(0, 200) / 1000, 3),
                'temperature' => $this->randomTemperature($currentTime),
                'humidity' => $this->randomHumidity($currentTime),
                'rain' => $this->randomRain($currentTime),
                'wind_speed' => $this->randomWindSpeed($currentTime),
                'wind_direction' => rand(0, 360),
                'air_pressure' => round(rand(9700, 10400) / 10, 1),
                'noise' => round(rand(300, 1000) / 10, 1),
                'lead' => round(rand(0, 500) / 1000, 3),
                'lead_temperature' => round(rand(150, 400) / 10, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $dataBatch[] = $data;
            $totalRecords++;

            if (count($dataBatch) >= $batchSize) {
                $connection->table('sensor_data')->insert($dataBatch);
                $dataBatch = [];
            }

            $currentTime->addMinutes($intervalMinutes);
        }

        if (!empty($dataBatch)) {
            $connection->table('sensor_data')->insert($dataBatch);
        }

        return $totalRecords;
    }

    private function randomTemperature($time): float
    {
        $hour = $time->hour;
        $baseTemp = rand(180, 350) / 10;
        $dailyVariation = 3 * sin(($hour - 8) * pi() / 12);
        $temp = $baseTemp + $dailyVariation + rand(-20, 20) / 10;
        return round(max(10, min(45, $temp)), 1);
    }

    private function randomHumidity($time): float
    {
        $hour = $time->hour;
        $baseHumidity = rand(400, 850) / 10;
        $dailyVariation = 8 * sin(($hour + 6) * pi() / 12);
        $humidity = $baseHumidity + $dailyVariation + rand(-50, 50) / 10;
        return round(max(20, min(100, $humidity)), 1);
    }

    private function randomRain($time): float
    {
        // 20% chance of rain
        if (rand(1, 100) <= 20) {
            return round(rand(1, 500) / 10, 1);
        }
        return 0;
    }

    private function randomWindSpeed($time): float
    {
        $hour = $time->hour;
        $baseWind = rand(0, 150) / 10;
        $dailyPattern = 2 * sin(($hour - 6) * pi() / 12);
        $wind = $baseWind + $dailyPattern + rand(-10, 10) / 10;
        return round(max(0, $wind), 1);
    }
}
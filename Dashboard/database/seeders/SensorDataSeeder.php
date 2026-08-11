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
            $records = $this->generateStationData($connection, $station);
            $totalRecords += $records;
            $bar->advance();
        }

        $bar->finish();
        $this->command->newLine();
        $this->command->info("Generated {$totalRecords} sensor data records successfully!");
    }

    /**
     * Generate data for a single station
     */
    private function generateStationData($connection, $station): int
    {
        // Generate 7 days of data with random intervals
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        $currentTime = clone $startDate;
        
        $dataBatch = [];
        $batchSize = 500;
        $totalRecords = 0;

        while ($currentTime <= $endDate) {
            // Random interval between 3-15 minutes
            $intervalMinutes = rand(3, 15);
            
            $data = [
                'station_mn' => $station->station_mn,
                'ip_address' => $station->lead_ip,
                'data_time' => $currentTime->format('Y-m-d H:i:s'),
                'pm25' => $this->randomPM25(),
                'pm10' => $this->randomPM10(),
                'tsp' => $this->randomTSP(),
                'ozone' => $this->randomOzone(),
                'carbon_monoxide' => $this->randomCO(),
                'sulfur_dioxide' => $this->randomSO2(),
                'nitrogen_dioxide' => $this->randomNO2(),
                'temperature' => $this->randomTemperature($currentTime),
                'humidity' => $this->randomHumidity($currentTime),
                'rain' => $this->randomRain($currentTime),
                'wind_speed' => $this->randomWindSpeed($currentTime),
                'wind_direction' => rand(0, 360),
                'air_pressure' => $this->randomAirPressure(),
                'noise' => $this->randomNoise(),
                'lead' => $this->randomLead(),
                'lead_temperature' => $this->randomLeadTemperature(),
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

        // Insert remaining records
        if (!empty($dataBatch)) {
            $connection->table('sensor_data')->insert($dataBatch);
        }

        return $totalRecords;
    }

    /**
     * Random PM2.5 values (0-100 µg/m³)
     */
    private function randomPM25(): float
    {
        return round(rand(0, 1000) / 10, 1);
    }

    /**
     * Random PM10 values (0-200 µg/m³)
     */
    private function randomPM10(): float
    {
        return round(rand(0, 2000) / 10, 1);
    }

    /**
     * Random TSP values (0-400 µg/m³)
     */
    private function randomTSP(): float
    {
        return round(rand(0, 4000) / 10, 1);
    }

    /**
     * Random Ozone values (0-0.2 ppm)
     */
    private function randomOzone(): float
    {
        return round(rand(0, 200) / 1000, 3);
    }

    /**
     * Random Carbon Monoxide values (0-10 ppm)
     */
    private function randomCO(): float
    {
        return round(rand(0, 1000) / 100, 2);
    }

    /**
     * Random Sulfur Dioxide values (0-0.1 ppm)
     */
    private function randomSO2(): float
    {
        return round(rand(0, 100) / 1000, 3);
    }

    /**
     * Random Nitrogen Dioxide values (0-0.2 ppm)
     */
    private function randomNO2(): float
    {
        return round(rand(0, 200) / 1000, 3);
    }

    /**
     * Random Temperature (15-40°C) with slight diurnal variation
     */
    private function randomTemperature($time): float
    {
        $hour = $time->hour;
        // Slight diurnal pattern: warmer during day, cooler at night
        $baseTemp = rand(180, 350) / 10;
        $dailyVariation = 3 * sin(($hour - 8) * pi() / 12);
        $temp = $baseTemp + $dailyVariation + rand(-20, 20) / 10;
        return round(max(10, min(45, $temp)), 1);
    }

    /**
     * Random Humidity (30-95%)
     */
    private function randomHumidity($time): float
    {
        $hour = $time->hour;
        // Slight diurnal pattern: higher at night
        $baseHumidity = rand(400, 850) / 10;
        $dailyVariation = 8 * sin(($hour + 6) * pi() / 12);
        $humidity = $baseHumidity + $dailyVariation + rand(-50, 50) / 10;
        return round(max(20, min(100, $humidity)), 1);
    }

    /**
     * Random Rain (0-50mm)
     */
    private function randomRain($time): float
    {
        // 20% chance of rain
        if (rand(1, 100) <= 20) {
            return round(rand(1, 500) / 10, 1);
        }
        return 0;
    }

    /**
     * Random Wind Speed (0-20 m/s)
     */
    private function randomWindSpeed($time): float
    {
        $hour = $time->hour;
        // Slightly stronger winds during afternoon
        $baseWind = rand(0, 150) / 10;
        $dailyPattern = 2 * sin(($hour - 6) * pi() / 12);
        $wind = $baseWind + $dailyPattern + rand(-10, 10) / 10;
        return round(max(0, $wind), 1);
    }

    /**
     * Random Air Pressure (980-1030 hPa)
     */
    private function randomAirPressure(): float
    {
        return round(rand(9800, 10300) / 10, 1);
    }

    /**
     * Random Noise (30-100 dB)
     */
    private function randomNoise(): float
    {
        return round(rand(300, 1000) / 10, 1);
    }

    /**
     * Random Lead (0.001-0.5 µg/m³)
     */
    private function randomLead(): float
    {
        return round(rand(1, 500) / 1000, 3);
    }

    /**
     * Random Lead Temperature (15-40°C)
     */
    private function randomLeadTemperature(): float
    {
        return round(rand(150, 400) / 10, 1);
    }
}
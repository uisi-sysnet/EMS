<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SensorData;
use App\Models\Station;
use Carbon\Carbon;

class SensorDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all active stations
        $stations = Station::where('deleted', false)->get();

        if ($stations->isEmpty()) {
            $this->command->warn('No stations found. Please run StationSeeder first.');
            return;
        }

        $this->command->info('Generating sensor data for ' . $stations->count() . ' stations...');

        // Use the factory to generate data
        foreach ($stations as $station) {
            // Generate 1000 records per station
            SensorData::factory()
                ->count(1000)
                ->create([
                    'station_mn' => $station->station_mn,
                ]);
        }

        $this->command->info('Sensor data seeding completed successfully!');
    }
}
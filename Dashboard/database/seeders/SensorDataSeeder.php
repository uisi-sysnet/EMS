<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SensorData;
use App\Models\Station;

class SensorDataFactory extends Factory
{
    protected $model = SensorData::class;

    public function definition(): array
    {
        $station = Station::inRandomOrder()->first();
        $time = $this->faker->dateTimeBetween('-30 days', 'now');
        
        return [
            'station_mn' => $station->station_mn,
            'ip_address' => $station->lead_ip ?? $this->faker->ipv4,
            'data_time' => $time,
            'pm25' => $this->faker->randomFloat(2, 5, 50),
            'pm10' => $this->faker->randomFloat(2, 10, 100),
            'tsp' => $this->faker->randomFloat(2, 20, 200),
            'ozone' => $this->faker->randomFloat(3, 0.01, 0.15),
            'carbon_monoxide' => $this->faker->randomFloat(2, 0.1, 5),
            'sulfur_dioxide' => $this->faker->randomFloat(3, 0.001, 0.05),
            'nitrogen_dioxide' => $this->faker->randomFloat(3, 0.005, 0.1),
            'temperature' => $this->faker->randomFloat(1, 15, 35),
            'humidity' => $this->faker->randomFloat(1, 30, 80),
            'rain' => $this->faker->randomFloat(1, 0, 10),
            'wind_speed' => $this->faker->randomFloat(1, 0, 15),
            'wind_direction' => $this->faker->numberBetween(0, 360),
            'air_pressure' => $this->faker->randomFloat(2, 980, 1030),
            'noise' => $this->faker->randomFloat(1, 30, 90),
            'lead' => $this->faker->randomFloat(3, 0.01, 0.5),
            'lead_temperature' => $this->faker->randomFloat(1, 20, 30),
        ];
    }
}
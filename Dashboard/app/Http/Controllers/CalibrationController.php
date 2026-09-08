<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalibrationController extends Controller
{
    /**
     * Display the calibration page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Sample data - replace with your actual data source
        $calibrations = $this->getSampleCalibrations();
        
        return view('settings.calibration', compact('calibrations'));
    }

    /**
     * Get sample calibration data.
     * Replace this with actual database queries.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getSampleCalibrations()
    {
        // This is sample data - replace with your actual model
        return collect([
            (object) [
                'id' => 1,
                'source' => 'sensor',
                'file_path' => 'uploads/calibration/sensor_calibration_2026-01.xlsx',
                'checklist' => ['Temperature', 'Humidity', 'PM2.5', 'PM10'],
                'total_data' => 15423,
                'requests_per_min' => 45,
                'created_at' => now()->subDays(2),
            ],
            (object) [
                'id' => 2,
                'source' => 'manual',
                'file_path' => 'uploads/calibration/manual_check_2026-01-15.pdf',
                'checklist' => ['Pressure', 'CO', 'NO2', 'O3'],
                'total_data' => 892,
                'requests_per_min' => 12,
                'created_at' => now()->subDays(5),
            ],
            (object) [
                'id' => 3,
                'source' => 'sensor',
                'file_path' => 'uploads/calibration/sensor_calibration_2026-02.xlsx',
                'checklist' => ['Temperature', 'Humidity', 'Pressure', 'PM2.5', 'PM10', 'CO', 'NO2', 'O3'],
                'total_data' => 28145,
                'requests_per_min' => 62,
                'created_at' => now()->subDay(),
            ],
            (object) [
                'id' => 4,
                'source' => 'external',
                'file_path' => 'uploads/calibration/external_audit_q1_2026.pdf',
                'checklist' => ['Temperature', 'Humidity', 'Pressure'],
                'total_data' => 456,
                'requests_per_min' => 8,
                'created_at' => now()->subDays(10),
            ],
            (object) [
                'id' => 5,
                'source' => 'sensor',
                'file_path' => 'uploads/calibration/sensor_calibration_2025-12.xlsx',
                'checklist' => ['PM2.5', 'PM10', 'CO'],
                'total_data' => 12389,
                'requests_per_min' => 38,
                'created_at' => now()->subDays(15),
            ],
        ]);
    }
}
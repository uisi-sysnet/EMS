<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CamerasFormatExport implements FromArray, WithHeadings, WithStyles
{
    public function array(): array
    {
        return [
            [
                'Channel 1',
                'Camera Name Example',
                'camera-name-example',
                'Main Building',
                '192.168.1.100',
                '80',
                'admin',
                'password123',
                'profile_001',
                'rtsp://192.168.1.100:554/stream',
                'IP Camera',
                'SN-2024-001',
                '14.5995',
                '120.9842',
                '1',
                '2024-01-01 00:00:00',
                'online',
                '',
                'Sample camera notes',
                '0'
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'channel',
            'name',
            'slug',
            'location',
            'ip_address',
            'onvif_port',
            'username',
            'password',
            'onvif_profile_token',
            'rtsp_uri',
            'device_type',
            'serial_number',
            'latitude',
            'longitude',
            'enabled',
            'last_synced_at',
            'last_status',
            'last_error',
            'notes',
            'deleted_at'
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        // Auto-size columns
        foreach (range('A', 'T') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return [
            1 => [
                'font' => [
                    'bold' => true, 
                    'color' => ['argb' => 'FFFFFF'], 
                    'size' => 12
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1a56db']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ]
        ];
    }
}
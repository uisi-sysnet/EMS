<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CamerasFormatExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function array(): array
    {
        return [
            [
                '1',                           // channel
                'Main Entrance Camera',        // name
                'main-entrance-camera',        // slug
                'Building A - Ground Floor',   // location
                '192.168.1.100',               // ip_address
                '80',                          // onvif_port
                'admin',                       // username
                'password123',                 // password
                'MainProfile',                 // onvif_profile_token
                'rtsp://192.168.1.100:554/stream1', // rtsp_uri
                'IP Camera',                   // device_type
                'SN-2024-001',                 // serial_number
                '14.5995',                     // latitude
                '120.9842',                    // longitude
                '1',                           // enabled (1=yes, 0=no)
                '2024-01-01 00:00:00',         // last_synced_at
                'online',                      // last_status
                '',                            // last_error
                'Main entrance monitoring',    // notes
                '0'                            // deleted_at (1=deleted, 0=active)
            ],
            [
                '2',
                'Parking Lot Camera',
                'parking-lot-camera',
                'Parking Area - East Wing',
                '192.168.1.101',
                '80',
                'admin',
                'password456',
                'ParkingProfile',
                'rtsp://192.168.1.101:554/stream2',
                'Dome Camera',
                'SN-2024-002',
                '14.5998',
                '120.9845',
                '1',
                '2024-01-01 00:00:00',
                'online',
                '',
                'Parking lot surveillance',
                '0'
            ],
            [
                '3',
                'Backup Camera',
                'backup-camera',
                'Storage Room',
                '192.168.1.102',
                '80',
                'admin',
                'password789',
                'BackupProfile',
                'rtsp://192.168.1.102:554/stream3',
                'PTZ Camera',
                'SN-2024-003',
                '14.6000',
                '120.9848',
                '0',
                '2024-01-01 00:00:00',
                'offline',
                'Connection timeout',
                'Needs troubleshooting',
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

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => '1a56db'], // Blue background
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ]);

        // Style the data rows with borders
        $sheet->getStyle('A2:T' . ($sheet->getHighestRow()))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'CCCCCC'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Add some notes/hints for users
        $sheet->setCellValue('V1', 'Instructions:');
        $sheet->setCellValue('V2', '1. Do not modify header row');
        $sheet->setCellValue('V3', '2. enabled: 1=Active, 0=Inactive');
        $sheet->setCellValue('V4', '3. deleted_at: 0=Active, 1=Deleted');
        $sheet->setCellValue('V5', '4. latitude & longitude: Use decimal format');
        $sheet->setCellValue('V6', '5. Required fields: channel, name, ip_address');
        
        // Style instructions
        $sheet->getStyle('V1:V6')->getFont()->setBold(true);
        $sheet->getStyle('V1:V6')->getFont()->setColor(['argb' => 'FF0000']);
        $sheet->getStyle('V1')->getFont()->setSize(12);
        
        return [];
    }
}
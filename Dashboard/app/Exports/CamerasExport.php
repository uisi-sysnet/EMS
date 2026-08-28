<?php

namespace App\Exports;

use App\Models\Camera;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CamerasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): Collection
    {
        return Camera::orderBy('name', 'asc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Channel',
            'Name',
            'Slug',
            'Location',
            'IP Address',
            'ONVIF Port',
            'Username',
            'Password',
            'ONVIF Profile Token',
            'RTSP URI',
            'Device Type',
            'Serial Number',
            'Latitude',
            'Longitude',
            'Status',
            'Last Synced At',
            'Notes',
            'Created At',
            'Updated At'
        ];
    }

    /**
     * @param Camera $camera
     * @return array
     */
    public function map($camera): array
    {
        return [
            $camera->id,
            $camera->channel,
            $camera->name,
            $camera->slug,
            $camera->location,
            $camera->ip_address,
            $camera->onvif_port,
            $camera->username,
            '', // Password - intentionally left empty for security
            $camera->onvif_profile_token,
            $camera->rtsp_uri,
            $camera->device_type,
            $camera->serial_number,
            $camera->latitude,
            $camera->longitude,
            $camera->enabled ? 'Active' : 'Inactive',
            $camera->last_synced_at ? $camera->last_synced_at->format('Y-m-d H:i:s') : null,
            $camera->notes,
            $camera->created_at ? $camera->created_at->format('Y-m-d H:i:s') : null,
            $camera->updated_at ? $camera->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet): array
    {
        // Style the header row
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1a56db'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Add border to all cells
        $sheet->getStyle('A1:T' . $sheet->getHighestRow())->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '374151'],
                ],
            ],
        ]);

        return [];
    }
}
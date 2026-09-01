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
use Illuminate\Support\Collection;

class CamerasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function collection(): Collection
    {
        return Camera::orderBy('name', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Channel',
            'Name',
            'IP Address',
            'Location',
            'Device Type',
            'Status',
            'Serial Number',
        ];
    }

    public function map($camera): array
    {
        return [
            $camera->id,
            $camera->channel,
            $camera->name,
            $camera->ip_address,
            $camera->location,
            $camera->device_type,
            $camera->enabled ? 'Active' : 'Inactive',
            $camera->serial_number,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
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

        return [];
    }
}
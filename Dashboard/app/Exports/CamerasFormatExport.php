<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Protection;

class CamerasFormatExport implements FromArray, WithHeadings, WithStyles, WithEvents
{
    public function array(): array
    {
        return [
            [
                'Channel 1',
                'Front Gate Camera',
                '', // Leave empty - will be auto-generated in import
                'Main Building',
                '192.168.1.100',
                '80',
                'admin',
                'password123',
                'profile_001',
                'rtsp://192.168.1.100:554/stream',
                'Bullet',
                'SN-2024-001',
                'Sample camera notes',
            ],
            [
                'Channel 2',
                'Back Entrance Camera',
                '', // Leave empty - will be auto-generated in import
                'Main Building',
                '192.168.1.101',
                '80',
                'admin',
                'password123',
                'profile_002',
                'rtsp://192.168.1.101:554/stream',
                'Dome',
                'SN-2024-002',
                'Back entrance monitoring',
            ],
            [
                'Channel 3',
                'Parking Lot PTZ',
                '', // Leave empty - will be auto-generated in import
                'Parking Area',
                '192.168.1.102',
                '80',
                'admin',
                'password123',
                'profile_003',
                'rtsp://192.168.1.102:554/stream',
                'PTZ',
                'SN-2024-003',
                'PTZ camera for parking surveillance',
            ],
        ];
    }

    public function headings(): array
    {
        return [
            'channel*',
            'name*',
            'slug (auto-generated)',
            'location*',
            'ip_address*',
            'onvif_port*',
            'username*',
            'password*',
            'onvif_profile_token',
            'rtsp_uri',
            'device_type*',
            'serial_number',
            'notes',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        // Auto-size all columns
        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
            $sheet->getColumnDimension($column)->setWidth(max($sheet->getColumnDimension($column)->getWidth(), 15));
        }

        // Set row height for header
        $sheet->getRowDimension(1)->setRowHeight(30);

        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFF'],
                    'size' => 11,
                    'name' => 'Arial'
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '1a56db'] // Dark blue
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ],
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['argb' => '1a56db']
                    ],
                    'inside' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '4b5563']
                    ]
                ]
            ],
            // Data rows styling
            2 => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true
                ],
                'borders' => [
                    'outline' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '4b5563']
                    ],
                    'inside' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => '4b5563']
                    ]
                ]
            ]
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Apply border to all data rows
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => '4b5563']
                        ]
                    ]
                ]);

                // --- HEADER COMMENTS ---
                // Add comments to explain the columns
                $sheet->getComment('B1')->getText()->createTextRun(
                    'The slug will be auto-generated from this name when imported.'
                );
                
                $sheet->getComment('C1')->getText()->createTextRun(
                    'Leave this column empty. It will be auto-generated from the name column.'
                );

                // --- SLUG COLUMN STYLING (Column C) ---
                // Color the slug column header differently to indicate auto-generation
                $sheet->getStyle('C1')->applyFromArray([
                    'font' => [
                        'color' => ['argb' => 'FFD700'], // Gold color
                        'bold' => true
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => '4b5563'] // Dark gray
                    ]
                ]);

                // Light gray background for slug column data rows
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getStyle('C' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'f3f4f6'] // Light gray
                        ],
                        'font' => [
                            'italic' => true,
                            'color' => ['argb' => '6b7280']
                        ]
                    ]);
                    // Add a placeholder text
                    $sheet->setCellValue('C' . $row, 'auto-generated');
                }

                // --- REQUIRED COLUMN INDICATORS (Add asterisk comments) ---
                $requiredColumns = ['A', 'B', 'D', 'E', 'F', 'G', 'H', 'K'];
                foreach ($requiredColumns as $column) {
                    $cell = $column . '1';
                    $sheet->getComment($cell)->getText()->createTextRun(
                        'Required field. Must not be empty.'
                    );
                }

                // --- DATA VALIDATION: Device Type (Column K) ---
                // Using fully qualified namespace for DataValidation
                $deviceTypes = ['PTZ', 'Bullet', 'Dome'];
                for ($row = 2; $row <= $highestRow; $row++) {
                    $cell = 'K' . $row;
                    $validation = $sheet->getCell($cell)->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Invalid Device Type');
                    $validation->setError('Please select from the dropdown list: PTZ, Bullet, or Dome.');
                    $validation->setPromptTitle('Select Device Type');
                    $validation->setPrompt('Choose a device type from the dropdown.');
                    $validation->setFormula1('"' . implode(',', $deviceTypes) . '"');
                }

                // --- DATA VALIDATION: ONVIF Port (Column F) ---
                for ($row = 2; $row <= $highestRow; $row++) {
                    $cell = 'F' . $row;
                    $validation = $sheet->getCell($cell)->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_WHOLE);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Invalid Port Number');
                    $validation->setError('Port must be between 1 and 65535.');
                    $validation->setPromptTitle('ONVIF Port');
                    $validation->setPrompt('Enter a valid port number (1-65535).');
                    $validation->setFormula1('1');
                    $validation->setFormula2('65535');
                }

                // --- DATA VALIDATION: Channel (Column A) - Alphanumeric only ---
                for ($row = 2; $row <= $highestRow; $row++) {
                    $cell = 'A' . $row;
                    $validation = $sheet->getCell($cell)->getDataValidation();
                    $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_CUSTOM);
                    $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setErrorTitle('Invalid Channel');
                    $validation->setError('Channel must be alphanumeric (letters and numbers only).');
                    $validation->setPromptTitle('Channel');
                    $validation->setPrompt('Enter a channel identifier (alphanumeric).');
                    $validation->setFormula1('=ISNUMBER(SEARCH("^[A-Za-z0-9]+$", A' . $row . '))');
                }

                // --- COLOR CODE: Required Columns (Gold asterisk) ---
                $requiredColumnsRange = ['A', 'B', 'D', 'E', 'F', 'G', 'H', 'K'];
                foreach ($requiredColumnsRange as $col) {
                    $sheet->getStyle($col . '1')->applyFromArray([
                        'font' => [
                            'color' => ['argb' => 'FFD700'] // Gold for asterisk
                        ]
                    ]);
                }

                // --- FREEZE PANES ---
                $sheet->freezePane('A2');

                // --- SET PRINT AREA ---
                $sheet->getPageSetup()->setPrintArea('A1:' . $highestColumn . $highestRow);

                // --- ALTERNATE ROW COLORS for better readability ---
                for ($row = 2; $row <= $highestRow; $row++) {
                    if ($row % 2 == 0) {
                        $sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'f8fafc'] // Very light gray
                            ]
                        ]);
                    }
                }
            }
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\BalanceReport;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BalanceReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings, WithStyles
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection(): Enumerable
    {
        return (new BalanceReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Asesor', 'Nombre Afiliado', 'Valor Saldo', 'Fecha de Ingreso'];
    }

    public function map($affiliate): array
    {
        /** @var Affiliate $affiliate */
        return [
            $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : '',
            trim("{$affiliate->name} {$affiliate->lastname}"),
            '$ ' . number_format((float) $affiliate->balance, 0, ',', '.'),
            $affiliate->validity,
        ];
    }

    public function drawings(): BaseDrawing|array
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

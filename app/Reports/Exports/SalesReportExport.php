<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\SalesReport;
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

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings, WithStyles
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection(): Enumerable
    {
        return (new SalesReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Fecha Venta', 'Desde', 'Hasta', 'Afiliación', 'Asesor', 'Nombre Afiliado', 'Franquicia', 'Tipo Venta', 'Valor Venta'];
    }

    public function map($affiliate): array
    {
        /** @var Affiliate $affiliate */
        $classification = SalesReport::classify($affiliate);

        return [
            $affiliate->payment_date,
            $classification['fecha_desde'],
            $affiliate->validity_end,
            $affiliate->validity,
            $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : '',
            trim("{$affiliate->name} {$affiliate->lastname}"),
            $affiliate->user->name ?? '',
            $classification['tipo_venta'],
            number_format((float) $classification['valor_venta'], 0, ',', '.'),
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

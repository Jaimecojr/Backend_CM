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
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
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
        $renovation = $affiliate->latestRenovation;
        $isRenewal  = $renovation !== null;

        return [
            $affiliate->payment_date,
            $isRenewal ? $renovation->date_ini : $affiliate->validity,
            $affiliate->validity_end,
            $affiliate->validity,
            $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : '',
            trim("{$affiliate->name} {$affiliate->lastname}"),
            $affiliate->user->name ?? '',
            $isRenewal ? 'Renovación' : 'Nuevo',
            number_format((float) ($isRenewal ? $renovation->value : $affiliate->value), 0, ',', '.'),
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
}

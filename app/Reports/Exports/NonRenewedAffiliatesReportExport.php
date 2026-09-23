<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\NonRenewedAffiliatesReport;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class NonRenewedAffiliatesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection(): Enumerable
    {
        return (new NonRenewedAffiliatesReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Hasta', 'Titular', 'Teléfono', 'Celular', 'Franquicia'];
    }

    public function map($affiliate): array
    {
        return [
            $affiliate->validity_end,
            trim("{$affiliate->name} {$affiliate->lastname}"),
            $affiliate->phone,
            $affiliate->movil,
            $affiliate->user->name ?? '',
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

<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\AffiliatesSummaryReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AffiliatesSummaryReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function array(): array
    {
        $indicators = (new AffiliatesSummaryReport())->indicators($this->filters, $this->authUser);

        return [
            ['Titulares', $indicators['titulares']],
            ['Titulares activos', $indicators['titulares_activos']],
            ['Titulares inactivos', $indicators['titulares_inactivos']],
            ['Beneficiarios', $indicators['beneficiarios']],
            ['Beneficiarios activos', $indicators['beneficiarios_activos']],
            ['Beneficiarios inactivos', $indicators['beneficiarios_inactivos']],
        ];
    }

    public function headings(): array
    {
        return ['Indicador', 'Cantidad'];
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

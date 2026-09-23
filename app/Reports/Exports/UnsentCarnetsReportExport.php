<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\UnsentCarnetsReport;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class UnsentCarnetsReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection(): Enumerable
    {
        return (new UnsentCarnetsReport())->failed($this->filters, $this->authUser);
    }

    public function headings(): array
    {
        return ['Fecha', 'Titular', 'Teléfono', 'Celular', 'Franquicia'];
    }

    public function map($message): array
    {
        return [
            substr((string) $message->created_at, 0, 10),
            trim("{$message->affiliate_name} {$message->affiliate_lastname}"),
            $message->affiliate_phone,
            $message->affiliate_movil,
            $message->franchise_name ?? '',
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

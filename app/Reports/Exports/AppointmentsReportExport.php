<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\AppointmentsReport;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AppointmentsReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection(): Enumerable
    {
        return (new AppointmentsReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Nombre', 'Médico', 'Ciudad', 'Fecha'];
    }

    public function map($appointment): array
    {
        return [
            AppointmentsReport::patientName($appointment),
            $appointment->doctor ? trim("{$appointment->doctor->name} {$appointment->doctor->lastname}") : '',
            $appointment->doctor?->city?->name ?? '',
            $appointment->date,
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

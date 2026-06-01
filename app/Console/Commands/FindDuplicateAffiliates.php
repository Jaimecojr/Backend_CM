<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FindDuplicateAffiliates extends Command
{
    protected $signature   = 'affiliates:find-duplicates {--output= : Ruta del archivo HTML de salida}';
    protected $description = 'Analiza afiliados duplicados por id_card y genera un reporte detallado (solo lectura)';

    public function handle(): void
    {
        $this->info('Buscando afiliados duplicados por id_card...');

        $duplicateCards = DB::table('affiliates')
            ->select('id_card', DB::raw('COUNT(*) as total'))
            ->whereNotNull('id_card')
            ->where('id_card', '!=', '')
            ->groupBy('id_card')
            ->having('total', '>', 1)
            ->orderByDesc('total')
            ->get();

        if ($duplicateCards->isEmpty()) {
            $this->info('No se encontraron id_card duplicados. La base de datos está limpia.');
            return;
        }

        $groups                    = [];
        $totalAffiliatesToDelete   = 0;
        $totalBeneficiariesToDelete = 0;
        $totalRenovationsToDelete  = 0;
        $totalNotesToDelete        = 0;

        foreach ($duplicateCards as $dup) {
            // Criterio: stade=1 primero, luego validity_end más reciente, luego id más alto
            $affiliates = Affiliate::with(['beneficiaries', 'renovations', 'notes', 'counselor', 'agreement'])
                ->where('id_card', $dup->id_card)
                ->orderByRaw('CASE WHEN stade = 1 THEN 0 ELSE 1 END ASC')
                ->orderByDesc('validity_end')
                ->orderByDesc('id')
                ->get();

            $keeper   = $affiliates->first();
            $toDelete = $affiliates->slice(1)->values();

            $benefCount = 0;
            $renovCount = 0;
            $notesCount = 0;

            foreach ($toDelete as $a) {
                $benefCount += $a->beneficiaries->count();
                $renovCount += $a->renovations->count();
                $notesCount += $a->notes->count();
            }

            $totalAffiliatesToDelete   += $toDelete->count();
            $totalBeneficiariesToDelete += $benefCount;
            $totalRenovationsToDelete  += $renovCount;
            $totalNotesToDelete        += $notesCount;

            $groups[] = [
                'id_card'                   => $dup->id_card,
                'total'                     => $dup->total,
                'keeper'                    => $keeper,
                'to_delete'                 => $toDelete,
                'beneficiaries_to_delete'   => $benefCount,
                'renovations_to_delete'     => $renovCount,
                'notes_to_delete'           => $notesCount,
            ];
        }

        // Resumen en consola
        $this->newLine();
        $this->line('  <fg=yellow>RESUMEN DEL ANÁLISIS</>');
        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['ID cards duplicados',        $duplicateCards->count()],
                ['Afiliados a eliminar',        $totalAffiliatesToDelete],
                ['Beneficiarios afectados',     $totalBeneficiariesToDelete],
                ['Renovaciones afectadas',      $totalRenovationsToDelete],
                ['Notas/Observaciones afectadas', $totalNotesToDelete],
            ]
        );

        // Distribución por tamaño de grupo
        $bySize = collect($groups)->groupBy(fn($g) => $g['total'])->map->count();
        $this->newLine();
        $this->line('  <fg=yellow>DISTRIBUCIÓN POR TAMAÑO DE GRUPO</>');
        $rows = [];
        foreach ($bySize->sortKeys() as $size => $count) {
            $rows[] = ["{$size} registros con mismo id_card", $count . ' casos'];
        }
        $this->table(['Duplicación', 'Cantidad de id_card'], $rows);

        // Generar reporte HTML
        $outputPath = $this->option('output') ?? storage_path('app/duplicados_afiliados.html');
        $html = $this->buildHtml($groups, $duplicateCards->count(), $totalAffiliatesToDelete, $totalBeneficiariesToDelete, $totalRenovationsToDelete, $totalNotesToDelete);

        file_put_contents($outputPath, $html);
        $this->newLine();
        $this->info("Reporte HTML generado en: {$outputPath}");
        $this->newLine();
    }

    private function buildHtml(
        array $groups,
        int $totalCards,
        int $totalAffiliates,
        int $totalBenef,
        int $totalRenov,
        int $totalNotes
    ): string {
        $fecha    = now()->format('d/m/Y H:i');
        $rows     = '';

        foreach ($groups as $g) {
            $keeper = $g['keeper'];
            $keeperName     = htmlspecialchars("{$keeper->name} {$keeper->lastname}");
            $keeperCard     = htmlspecialchars($keeper->id_card ?? '—');
            $keeperStade    = $keeper->stade == 1 ? '<span class="badge active">Activo</span>' : '<span class="badge inactive">Inactivo</span>';
            $keeperEnd      = $keeper->validity_end ?? '—';
            $keeperAgreement = htmlspecialchars($keeper->agreement->name ?? '—');
            $keeperCounselor = htmlspecialchars(($keeper->counselor->name ?? '') . ' ' . ($keeper->counselor->lastname ?? ''));

            $deleteRows = '';
            foreach ($g['to_delete'] as $a) {
                $aName      = htmlspecialchars("{$a->name} {$a->lastname}");
                $aStade     = $a->stade == 1 ? '<span class="badge active">Activo</span>' : '<span class="badge inactive">Inactivo</span>';
                $aEnd       = $a->validity_end ?? '—';
                $aAgreement = htmlspecialchars($a->agreement->name ?? '—');
                $aCounselor = htmlspecialchars(($a->counselor->name ?? '') . ' ' . ($a->counselor->lastname ?? ''));
                $aBenef     = $a->beneficiaries->count();
                $aRenov     = $a->renovations->count();
                $aNotes     = $a->notes->count();

                $deleteRows .= "
                <tr class='delete-row'>
                    <td>#{$a->id}</td>
                    <td>{$aName}</td>
                    <td>{$aStade}</td>
                    <td>{$aEnd}</td>
                    <td>{$aAgreement}</td>
                    <td>{$aCounselor}</td>
                    <td>{$aBenef}</td>
                    <td>{$aRenov}</td>
                    <td>{$aNotes}</td>
                    <td><span class='action-delete'>ELIMINAR</span></td>
                </tr>";
            }

            $rows .= "
            <div class='group-card'>
                <div class='group-header'>
                    <strong>CC / ID: {$keeperCard}</strong>
                    <span class='group-count'>{$g['total']} registros duplicados</span>
                    <span class='group-meta'>Beneficiarios a eliminar: {$g['beneficiaries_to_delete']} &nbsp;|&nbsp; Renovaciones: {$g['renovations_to_delete']} &nbsp;|&nbsp; Notas: {$g['notes_to_delete']}</span>
                </div>
                <table class='detail-table'>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Estado</th>
                            <th>Vence</th>
                            <th>Convenio</th>
                            <th>Asesor</th>
                            <th>Benef.</th>
                            <th>Renov.</th>
                            <th>Notas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class='keep-row'>
                            <td>#{$keeper->id}</td>
                            <td>{$keeperName}</td>
                            <td>{$keeperStade}</td>
                            <td>{$keeperEnd}</td>
                            <td>{$keeperAgreement}</td>
                            <td>{$keeperCounselor}</td>
                            <td>{$keeper->beneficiaries->count()}</td>
                            <td>{$keeper->renovations->count()}</td>
                            <td>{$keeper->notes->count()}</td>
                            <td><span class='action-keep'>CONSERVAR</span></td>
                        </tr>
                        {$deleteRows}
                    </tbody>
                </table>
            </div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reporte de Afiliados Duplicados — Contacto Médico</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #1a1a2e; padding: 32px 24px; }
    .page-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%); color: #fff; border-radius: 12px; padding: 36px 40px; margin-bottom: 32px; }
    .page-header h1 { font-size: 1.9rem; font-weight: 700; margin-bottom: 6px; }
    .page-header .meta { font-size: 0.88rem; color: #a0aec0; }
    .page-header .accent { color: #e8192c; font-weight: 700; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }
    .stat-card { background: #fff; border-radius: 10px; padding: 20px 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border-top: 4px solid #e8192c; }
    .stat-card .value { font-size: 2rem; font-weight: 800; color: #e8192c; }
    .stat-card .label { font-size: 0.8rem; color: #718096; margin-top: 4px; }

    .callout { border-left: 4px solid #f6ad55; background: #fffbf0; border-radius: 6px; padding: 14px 18px; margin-bottom: 28px; font-size: 0.9rem; color: #7b4f12; }
    .callout strong { display: block; margin-bottom: 4px; }

    .section-title { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e2e8f0; }

    .group-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; overflow: hidden; }
    .group-header { background: #f7fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 20px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .group-header strong { font-size: 1rem; color: #1a1a2e; }
    .group-count { background: #e8192c; color: #fff; font-size: 0.75rem; font-weight: 700; padding: 2px 10px; border-radius: 20px; }
    .group-meta { font-size: 0.78rem; color: #718096; margin-left: auto; }

    .detail-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .detail-table th { background: #edf2f7; color: #4a5568; font-weight: 600; padding: 9px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
    .detail-table td { padding: 9px 12px; border-bottom: 1px solid #f0f4f8; vertical-align: middle; }

    .keep-row { background: #f0fff4; }
    .keep-row td { color: #276749; }
    .delete-row { background: #fff5f5; }
    .delete-row td { color: #744210; }

    .badge { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
    .badge.active { background: #c6f6d5; color: #22543d; }
    .badge.inactive { background: #fed7d7; color: #742a2a; }

    .action-keep { display: inline-block; background: #48bb78; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 4px; }
    .action-delete { display: inline-block; background: #fc8181; color: #fff; font-size: 0.7rem; font-weight: 700; padding: 3px 10px; border-radius: 4px; }

    .legend { display: flex; gap: 20px; margin-bottom: 20px; font-size: 0.82rem; }
    .legend-item { display: flex; align-items: center; gap: 8px; }
    .legend-dot { width: 14px; height: 14px; border-radius: 3px; }
    .legend-dot.keep { background: #c6f6d5; }
    .legend-dot.del { background: #fed7d7; }

    @media print { body { background: #fff; padding: 0; } .group-card { box-shadow: none; border: 1px solid #e2e8f0; } }
  </style>
</head>
<body>

  <div class="page-header">
    <h1>Afiliados Duplicados — <span class="accent">Análisis de Datos</span></h1>
    <div class="meta">Generado el {$fecha} &nbsp;|&nbsp; Contacto Médico &nbsp;|&nbsp; Solo lectura — ningún registro fue modificado</div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="value">{$totalCards}</div>
      <div class="label">ID cards con duplicados</div>
    </div>
    <div class="stat-card">
      <div class="value">{$totalAffiliates}</div>
      <div class="label">Afiliados a eliminar</div>
    </div>
    <div class="stat-card">
      <div class="value">{$totalBenef}</div>
      <div class="label">Beneficiarios afectados</div>
    </div>
    <div class="stat-card">
      <div class="value">{$totalRenov}</div>
      <div class="label">Renovaciones afectadas</div>
    </div>
    <div class="stat-card">
      <div class="value">{$totalNotes}</div>
      <div class="label">Notas afectadas</div>
    </div>
  </div>

  <div class="callout">
    <strong>Criterio de selección aplicado</strong>
    Para cada grupo de duplicados se conserva el registro con mayor prioridad:
    <strong>1)</strong> Estado activo (<code>stade = 1</code>) primero &nbsp;
    <strong>2)</strong> Fecha de vencimiento más reciente &nbsp;
    <strong>3)</strong> ID más alto como desempate final.
    Los registros marcados como <em>ELIMINAR</em> y todos sus beneficiarios, renovaciones y notas asociados serán borrados permanentemente al ejecutar el comando de purga.
  </div>

  <div class="legend">
    <div class="legend-item"><div class="legend-dot keep"></div> Registro a CONSERVAR</div>
    <div class="legend-item"><div class="legend-dot del"></div> Registro a ELIMINAR</div>
  </div>

  <div class="section-title">Detalle por ID card duplicado</div>

  {$rows}

</body>
</html>
HTML;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateExpiredAffiliates extends Command
{
    protected $signature   = 'affiliates:update-expired';
    protected $description = 'Inactiva los afiliados cuya fecha de vencimiento es anterior a hoy';

    public function handle(): void
    {
        $hoy = Carbon::today()->toDateString();

        $total = Affiliate::where('stade', 1)
            ->where('validity_end', '<', $hoy)
            ->update(['stade' => 2]);

        $this->info("Afiliados inactivados: {$total}");
    }
}

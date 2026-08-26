<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Affiliate;
use Illuminate\Console\Command;

class UpdateExpiredAffiliates extends Command
{
    protected $signature   = 'affiliates:update-expired';
    protected $description = 'Inactiva los afiliados cuya fecha de vencimiento es anterior a hoy';

    public function handle(): void
    {
        $total = Affiliate::activeExpired()->update(['stade' => 2]);

        $this->info("Afiliados inactivados: {$total}");
    }
}

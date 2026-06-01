<?php

use App\Console\Commands\UpdateExpiredAffiliates;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Inactiva afiliados vencidos cada día a medianoche
Schedule::command(UpdateExpiredAffiliates::class)->dailyAt('00:05');

<?php

use App\Console\Commands\PurgeOldCarnets;
use App\Console\Commands\UpdateExpiredAffiliates;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Inactiva afiliados vencidos cada día a medianoche
Schedule::command(UpdateExpiredAffiliates::class)->dailyAt('00:05');

// Carnet PDFs carry personal data on the public disk and Meta only needs them at send time;
// they are kept 7 days as a margin in case a send is retried.
Schedule::command(PurgeOldCarnets::class)->dailyAt('03:00');

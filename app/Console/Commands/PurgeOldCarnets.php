<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes generated carnet PDFs older than the retention window.
 *
 * The PDFs contain personal data (name, ID card, beneficiaries) and live on the public disk only so
 * Meta can download them when the WhatsApp template is sent. Once Meta has fetched the file there is
 * no reason to keep it publicly reachable.
 */
class PurgeOldCarnets extends Command
{
    protected $signature = 'carnets:purge {--days=7 : Delete files older than this many days}';

    protected $description = 'Delete carnet PDFs older than the retention window from the public disk';

    public function handle(): int
    {
        $disk      = Storage::disk('public');
        $threshold = now()->subDays((int) $this->option('days'))->getTimestamp();
        $deleted   = 0;

        foreach ($disk->files('carnets') as $file) {
            if ($disk->lastModified($file) < $threshold) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Carnets eliminados: {$deleted}");

        return self::SUCCESS;
    }
}

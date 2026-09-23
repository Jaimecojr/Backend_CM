<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RegistAction;

class RegistActionLogger
{
    public function created(string $table, int $id): void
    {
        $this->log('I', $table, $id);
    }

    public function updated(string $table, int $id): void
    {
        $this->log('U', $table, $id);
    }

    public function statusChanged(string $table, int $id): void
    {
        $this->log('E', $table, $id);
    }

    public function deleted(string $table, int $id): void
    {
        $this->log('D', $table, $id);
    }

    private function log(string $actionType, string $table, int $id): void
    {
        RegistAction::create([
            'action_type'  => $actionType,
            'target_table' => $table,
            'table_id'     => $id,
            'user_id'      => auth()->id(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnsentCarnetsReport
{
    /**
     * Candidate carnet sends in the date range, joined to the matching
     * affiliate by stripping the '57' country prefix WhatsAppClient always
     * prepends (see WhatsAppClient::enviarPlantilla()). Super-admin only —
     * the controller returns 403 before this is ever called for a
     * franchise, so no AppliesFranchiseScope here.
     */
    private function candidates(array $filters, User $authUser): Builder
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $filters['to']   ?? now()->endOfMonth()->toDateString();

        $query = WhatsappMessage::query()
            ->select([
                'whatsapp_messages.*',
                'affiliates.name as affiliate_name',
                'affiliates.lastname as affiliate_lastname',
                'affiliates.phone as affiliate_phone',
                'affiliates.movil as affiliate_movil',
                'franchise.name as franchise_name',
            ])
            ->where('whatsapp_messages.type', 'carnet')
            ->whereRaw('DATE(whatsapp_messages.created_at) BETWEEN ? AND ?', [$from, $to])
            ->join('affiliates', function ($join) {
                $join->on('affiliates.movil', '=', DB::raw('SUBSTR(whatsapp_messages.recipient_id, 3)'));
            })
            ->leftJoin('users as franchise', 'franchise.id', '=', 'affiliates.user_id');

        if (!empty($filters['franchise_id'])) {
            $query->where('affiliates.user_id', $filters['franchise_id']);
        }

        return $query->orderByDesc('whatsapp_messages.created_at');
    }

    /** Messages Meta never confirmed with a messages[0].id. */
    public function failed(array $filters, User $authUser): Collection
    {
        return $this->candidates($filters, $authUser)
            ->get()
            ->reject(function ($message) {
                $decoded = json_decode($message->response, true);

                return !empty($decoded['messages'][0]['id'] ?? null);
            })
            ->values();
    }
}

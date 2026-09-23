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
     * Candidate carnet sends, joined to the matching affiliate by stripping
     * the '57' country prefix WhatsAppClient always prepends (see
     * WhatsAppClient::enviarPlantilla()). Super-admin only — the controller
     * returns 403 before this is ever called for a franchise, so no
     * AppliesFranchiseScope here.
     *
     * No date filter on purpose: this report is a live "still unresolved"
     * list, not a historical log. A carnet leaves it the moment it sends
     * successfully (see failed() below), so scoping it to a month window
     * would hide already-fixed failures from before the window while still
     * showing nothing useful about ones that just happened to land outside
     * it — there is no month boundary that makes sense for "what's broken
     * right now". Confirmed with the product owner.
     *
     * $authUser is accepted but unused: it's kept for interface symmetry
     * with every other *Report::query()/failed() method in this module, so
     * a future franchise-scoping addition here has an obvious place to use
     * it. The actual super-admin-only check happens in
     * ReportController::unsentCarnets() before this is ever called.
     */
    private function candidates(array $filters, User $authUser): Builder
    {
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

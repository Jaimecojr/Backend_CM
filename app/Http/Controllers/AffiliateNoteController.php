<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateNote;
use Illuminate\Http\Request;

class AffiliateNoteController extends Controller
{
    /**
     * List notes of an affiliate (most recent first)
     */
    public function index(Affiliate $affiliate)
    {
        $notes = $affiliate->notes()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'affiliate_id', 'user_id', 'body', 'created_at']);

        return response()->json([
            'message' => 'Notas obtenidas correctamente',
            'data'    => $notes,
        ]);
    }

    /**
     * Create a new note
     */
    public function store(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $note = $affiliate->notes()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        $note->load('user:id,name');

        return response()->json([
            'message' => 'Nota creada correctamente',
            'data'    => $note,
        ], 201);
    }

    /**
     * Delete a note (type 1 users only)
     */
    public function destroy(Request $request, Affiliate $affiliate, AffiliateNote $note)
    {
        // Only super admin (type == 1) can delete
        if (!$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar notas.',
            ], 403);
        }

        // Verify that the note belongs to the affiliate
        if ($note->affiliate_id !== $affiliate->id) {
            return response()->json(['message' => 'Nota no encontrada.'], 404);
        }

        $note->delete();

        return response()->json(['message' => 'Nota eliminada correctamente.']);
    }
}

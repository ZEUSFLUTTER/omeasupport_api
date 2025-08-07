<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Rapport;
use Exception;

class RapportController extends Controller
{
    public function store(Request $request, Ticket $ticket)
    {
        try {
            $validated = $request->validate([
                'solution' => 'required|string',
                'duree' => 'required|string|max:20',
                'prix' => 'required|numeric',
                'statut' => 'required|string|in:completed,suspendu',
            ]);

            if ($ticket->statut !== 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'Le rapport ne peut être créé que pour un ticket "completed". Statut actuel: ' . $ticket->statut . '.',
                ], 400);
            }

            $technicianId = auth()->id();

            $rapport = Rapport::create([
                'ticket_id' => $ticket->id,
                'client_id' => $ticket->user_id,
                'technicien_id' => $technicianId,
                'solution' => $validated['solution'],
                'duree' => $validated['duree'],
                'prix' => $validated['prix'],
                'statut' => $validated['statut'],
                'date_intervention' => now()->toDateString(),
            ]);

            $ticket->statut = $validated['statut'];
            $ticket->save();

            return response()->json([
                'status' => true,
                'message' => 'Rapport enregistré avec succès.',
                'rapport' => $rapport,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Une erreur est survenue lors de la création du rapport.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Rapport;
use Exception; // Assurez-vous d'importer Exception

class RapportController extends Controller
{
    /**
     * Enregistre un rapport pour un ticket.
     * Le rapport ne peut être créé que pour un ticket ayant le statut 'completed'.
     *
     * @param Request $request
     * @param Ticket $ticket (Laravel fera automatiquement l'injection de modèle ici)
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Ticket $ticket)
    {
        try {
            // Validation des champs reçus (solution, duree, prix, statut)
            $validated = $request->validate([
                'solution' => 'required|string',
                'duree' => 'required|string|max:20',
                'prix' => 'required|numeric',
                'statut' => 'required|string|in:completed,suspendu', // Le statut du rapport peut être completed ou suspendu
            ]);

            // Vérifier que le ticket est bien terminé pour pouvoir créer un rapport
            if ($ticket->statut !== 'completed') {
                return response()->json([
                    'status' => false,
                    'message' => 'Le rapport ne peut être créé que pour un ticket "completed". Statut actuel: ' . $ticket->statut . '.',
                ], 400); // 400 Bad Request
            }

            // Vérifier que le technicien est bien celui connecté (peut-être pas nécessaire si la route est protégée par middleware et que l'ID est tiré de auth()->id())
            $technicianId = auth()->id();
            // Optionnel : Vérifier si ce technicien était assigné au ticket ou a géré l'intervention
            // if ($ticket->technician_id !== $technicianId) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Non autorisé à créer un rapport pour ce ticket.',
            //     ], 403);
            // }

            // Création du rapport
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

            // Mise à jour du statut du ticket (si le rapport change le statut final du ticket)
            // Si le rapport dit 'suspendu', le ticket pourrait repasser à 'suspendu'
            // Si le rapport dit 'completed', le ticket reste 'completed'
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
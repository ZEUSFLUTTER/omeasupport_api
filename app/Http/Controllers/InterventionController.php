<?php

namespace App\Http\Controllers;

use App\Models\Intervention;
// use App\Http\Requests\StoreInterventionRequest; // Non utilisés ici
// use App\Http\Requests\UpdateInterventionRequest; // Non utilisés ici
use App\Models\Ticket;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\User;
use Exception; // Assurez-vous d'importer Exception

class InterventionController extends Controller
{
    // Cette méthode est une relation Eloquent et devrait être dans un modèle.
    public function ticket()
    {
        // Ceci est une méthode de relation Eloquent, pas une action de contrôleur API directe.
        throw new Exception("This method should not be called as an API endpoint.");
    }

    /**
     * Ceci est un DUPICATA de la méthode startIntervention dans TechnicianController.
     * Il est recommandé de n'en conserver qu'une seule et de s'assurer que vos routes
     * pointent vers la bonne. La version dans TechnicianController a été mise à jour.
     * Cette version est laissée telle quelle pour la référence mais ne devrait pas être active.
     *
     * @param int $ticketId
     * @return \Illuminate\Http\JsonResponse
     */
    public function startIntervention($ticketId)
    {
        try {
            $ticket = Ticket::find($ticketId);
            if (!$ticket) {
                return response()->json(['status' => false, 'message' => 'Ticket non trouvé'], 404);
            }

            // Vérification du statut (dupliqué ici pour la référence, mais utiliser la version TechnicianController)
            if (in_array($ticket->statut, ['completed', 'in_progress', 'suspendu'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Impossible de démarrer l\'intervention. Le ticket est déjà ' . $ticket->statut . '.',
                ], 400);
            }

            $intervention = Intervention::firstOrCreate(
                ['ticket_id' => $ticket->id, 'technician_id' => auth()->id()],
                ['client_id' => $ticket->user_id, 'date_prise_en_charge' => now()->toDateString()]
            );

            if (!$intervention->heure_debut) {
                $intervention->heure_debut = now();
                $intervention->save();
            }

            // Mettre à jour le statut du ticket (dupliqué ici)
            $ticket->statut = 'in_progress';
            $ticket->save();

            return response()->json([
                'status' => true,
                'message' => 'Session démarrée avec succès',
                'heure_debut' => $intervention->heure_debut->toDateTimeString(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Une erreur est survenue lors du démarrage de l\'intervention.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
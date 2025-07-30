<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Intervention; // Assurez-vous d'importer Intervention
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class TechnicianController extends Controller
{
    // ... autres méthodes

    public function read(Request $request)
    {
        try {
            $user = $request->user(); // Le technicien connecté

            // Statistiques du tableau de bord
            $ticketsToday = Ticket::whereDate('created_at', today())->count(); // Tickets créés aujourd'hui
            $pendingTickets = Ticket::where('statut', 'pending')->count();
            $completedTickets = Ticket::where('statut', 'completed')->count(); // Tous les tickets terminés
            // Calcul fictif pour la distance (ou à adapter avec des données réelles)
            $distanceTraveled = 0.0; // À implémenter si vous suivez la distance

            // Ticket actif du technicien (celui en cours d'intervention)
            $activeIntervention = Intervention::where('technician_id', $user->id)
                                        ->whereNull('heure_fin') // Intervention non terminée
                                        ->with('ticket') // Charge le ticket associé
                                        ->first();

            $activeTicket = null;
            if ($activeIntervention && $activeIntervention->ticket) {
                $activeTicket = $activeIntervention->ticket;
                // Assurez-vous que le statut du ticket est 'in_progress' si l'intervention est active
                if ($activeTicket->statut !== 'in_progress') {
                    $activeTicket->statut = 'in_progress';
                    $activeTicket->save();
                }
            }

            // Tickets récents pour le technicien (peut-être ceux qui lui sont assignés ou qu'il a acceptés)
            // Pour cet exemple, je vais juste prendre les 3 derniers tickets assignés à des techniciens (ouverts)
            // Adaptez cette logique en fonction de comment vous assignez les tickets aux techniciens
            $recentTickets = Ticket::whereIn('statut', ['pending', 'assign', 'in_progress'])
                                ->orderBy('created_at', 'desc')
                                ->take(3)
                                ->get(); // Vous pouvez ajouter ->where('assigned_to_technician_id', $user->id) si vous avez une colonne d'assignation

            // Tous les tickets (pour l'onglet Tickets) - potentiellement à filtrer pour le technicien
            $allTickets = Ticket::latest()->get(); // Ceci renvoie tous les tickets, pas seulement ceux du technicien.
                                                // Adaptez si le technicien ne doit voir que certains tickets.

            return response()->json([
                'status' => true,
                'message' => 'Données du tableau de bord et tickets pour le technicien',
                'dashboard_summary' => [
                    'tickets_today' => $ticketsToday,
                    'pending_tickets' => $pendingTickets,
                    'completed_tickets' => $completedTickets,
                    'distance_traveled' => $distanceTraveled,
                ],
                'active_ticket' => $activeTicket ? [
                    'id' => $activeTicket->id,
                    'title' => $activeTicket->type_probleme, // Utilisez type_probleme pour le titre
                    'location' => $activeTicket->adresse,    // Utilisez adresse pour la localisation
                    'assigned_to' => $user->nom . ' ' . $user->prenom, // Ou le nom du technicien assigné au ticket si différent
                    'time' => $activeTicket->date_rdv ? Carbon::parse($activeTicket->date_rdv)->format('H:i') : null, // Heure du RDV
                    'status' => $activeTicket->statut,
                    'priority' => $activeTicket->priority ?? 'medium', // Ajoutez une colonne 'priority' à votre modèle Ticket
                    'is_active' => true,
                ] : null,
                'recent_tickets' => $recentTickets->map(function($ticket) {
                    return [
                        'id' => $ticket->id,
                        'title' => $ticket->type_probleme,
                        'location' => $ticket->adresse,
                        'assigned_to' => $ticket->technician_id ? User::find($ticket->technician_id)->nom : 'Non assigné', // Si vous avez une colonne technician_id
                        'time' => $ticket->date_rdv ? Carbon::parse($ticket->date_rdv)->format('H:i') : null,
                        'status' => $ticket->statut,
                        'priority' => $ticket->priority ?? 'medium',
                    ];
                }),
                'all_tickets' => $allTickets->map(function($ticket) {
                    return [
                        'id' => $ticket->id,
                        'title' => $ticket->type_probleme,
                        'location' => $ticket->adresse,
                        'assigned_to' => $ticket->technician_id ? User::find($ticket->technician_id)->nom : 'Non assigné',
                        'time' => $ticket->date_rdv ? Carbon::parse($ticket->date_rdv)->format('H:i') : null,
                        'status' => $ticket->statut,
                        'priority' => $ticket->priority ?? 'medium',
                        'is_active' => ($ticket->statut == 'in_progress'), // Définir 'is_active' dynamiquement
                    ];
                }),
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur lors de la récupération des données du tableau de bord',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ... vos autres méthodes

    public function startIntervention($ticketId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket non trouvé'], 404);
        }

        // *** CORRECTION ICI : Le statut du ticket doit passer à 'in_progress' au démarrage ***
        // Et seulement si ce n'est pas déjà le cas
        if ($ticket->statut !== 'in_progress') {
            $ticket->statut = 'in_progress';
            $ticket->save();
        }


        $intervention = Intervention::firstOrCreate(
            ['ticket_id' => $ticket->id, 'technician_id' => auth()->id()],
            ['client_id' => $ticket->user_id, 'date_prise_en_charge' => now()->toDateString()]
        );

        if (!$intervention->heure_debut) {
            $intervention->heure_debut = now();
            $intervention->save();
        }

        return response()->json([
            'message' => 'Session démarrée avec succès',
            'heure_debut' => $intervention->heure_debut->toDateTimeString(),
            'ticket_status' => $ticket->statut, // Confirmer le statut
        ]);
    }

    public function endIntervention($ticketId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket non trouvé'], 404);
        }

        $intervention = Intervention::where('ticket_id', $ticket->id)
            ->where('technician_id', auth()->id())
            ->whereNull('heure_fin') // S'assurer qu'on termine une intervention active
            ->first();

        if (!$intervention || !$intervention->heure_debut) {
            return response()->json(['message' => 'Intervention non commencée ou déjà terminée'], 400);
        }

        $heureFinStr = now()->format('H:i:s');
        $intervention->heure_fin = $heureFinStr;
        $intervention->save();

        // *** CORRECTION ICI : Le statut du ticket doit passer à 'completed' à la fin ***
        $ticket->statut = 'completed';
        $ticket->save();

        return response()->json([
            'heure_debut' => $intervention->heure_debut,
            'heure_fin' => $heureFinStr,
            'message' => 'Intervention terminée',
            'ticket_status' => $ticket->statut, // Confirmer le statut
        ]);
    }

}
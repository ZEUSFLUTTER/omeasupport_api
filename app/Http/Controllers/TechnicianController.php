<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Ticket;
use App\Models\Intervention;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Exception;

class TechnicianController extends Controller
{
    public function read(Request $request)
    {
        try {
            $user = $request->user();

            $ticketsToday = Ticket::whereDate('created_at', today())->count();
            $pendingTickets = Ticket::where('statut', 'pending')->count();
            $completedTickets = Ticket::where('statut', 'completed')->count();
            $distanceTraveled = 0.0;

            $activeIntervention = Intervention::where('technician_id', $user->id)
                                            ->whereNull('heure_fin')
                                            ->with('ticket')
                                            ->first();

            $activeTicket = null;
            if ($activeIntervention && $activeIntervention->ticket) {
                $activeTicket = $activeIntervention->ticket;
                if ($activeTicket->statut !== 'in_progress') {
                    $activeTicket->statut = 'in_progress';
                    $activeTicket->save();
                }
            }

            $recentTickets = Ticket::whereIn('statut', ['pending', 'assign', 'in_progress'])
                                ->orderBy('created_at', 'desc')
                                ->take(3)
                                ->get();

            $allTickets = Ticket::latest()->get();

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
                    'title' => $activeTicket->type_probleme,
                    'location' => $activeTicket->adresse,
                    'assigned_to' => $user->nom . ' ' . $user->prenom,
                    'time' => $activeTicket->date_rdv ? Carbon::parse($activeTicket->date_rdv)->format('H:i') : null,
                    'status' => $activeTicket->statut,
                    'priority' => $activeTicket->priority ?? 'medium',
                    'is_active' => true,
                ] : null,
                'recent_tickets' => $recentTickets->map(function($ticket) {
                    return [
                        'id' => $ticket->id,
                        'title' => $ticket->type_probleme,
                        'location' => $ticket->adresse,
                        'assigned_to' => $ticket->technician_id ? User::find($ticket->technician_id)->nom : 'Non assigné',
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
                        'is_active' => ($ticket->statut == 'in_progress'),
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

    public function startIntervention($ticketId)
    {
        $ticket = Ticket::find($ticketId);
        if (!$ticket) {
            return response()->json(['message' => 'Ticket non trouvé'], 404);
        }

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
            'ticket_status' => $ticket->statut,
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
            ->whereNull('heure_fin')
            ->first();

        if (!$intervention || !$intervention->heure_debut) {
            return response()->json(['message' => 'Intervention non commencée ou déjà terminée'], 400);
        }

        $heureFinStr = now()->format('H:i:s');
        $intervention->heure_fin = $heureFinStr;
        $intervention->save();

        $ticket->statut = 'completed';
        $ticket->save();

        return response()->json([
            'heure_debut' => $intervention->heure_debut,
            'heure_fin' => $heureFinStr,
            'message' => 'Intervention terminée',
            'ticket_status' => $ticket->statut,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'type_probleme' => 'required|string|max:255',
                'description' => 'required|string',
                'adresse' => 'required|string|max:255',
                'photos' => 'nullable|array',
                'photos.*' => 'string',
                'date_rdv' => 'required|date',
            ]);

            $ticket = Ticket::create([
                'user_id' => auth()->id(),
                'type_probleme' => $validated['type_probleme'],
                'description' => $validated['description'],
                'adresse' => $validated['adresse'],
                'photos' => $validated['photos'] ?? [],
                'date_rdv' => $validated['date_rdv'],
                'statut' => 'pending',
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Ticket créé avec succès.',
                'ticket' => $ticket,
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
                'message' => 'Une erreur est survenue lors de la création du ticket.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $tickets = $request->user()->tickets()->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Liste des tickets',
                'data' => $tickets
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur lors de la récupération des tickets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $ticket = Ticket::where('user_id', $request->user()->id)->find($id);

            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Ticket introuvable',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Détails du ticket',
                'data' => $ticket
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur lors de la récupération du ticket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $ticket = Ticket::findOrFail($id);

            if (auth()->user()->id !== $ticket->user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Non autorisé à supprimer ce ticket.',
                ], 403);
            }

            if (in_array($ticket->statut, ['completed', 'in_progress', 'suspendu'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Impossible de supprimer un ticket ' . $ticket->statut . '.',
                ], 400);
            }

            $ticket->delete();

            return response()->json([
                'status' => true,
                'message' => 'Ticket supprimé avec succès.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Ticket introuvable.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Une erreur est survenue lors de la suppression du ticket.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reschedule(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_rdv' => 'required|date|after:now',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $ticket = Ticket::where('user_id', $request->user()->id)->find($id);

            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Ticket introuvable',
                ], 404);
            }

            if (in_array($ticket->statut, ['completed', 'in_progress', 'suspendu'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Impossible de replanifier un ticket ' . $ticket->statut . '.',
                ], 400);
            }

            $ticket->update([
                'date_rdv' => $request->date_rdv,
                'statut' => 'planified'
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Rendez-vous replanifié',
                'data' => $ticket
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur lors de la replanification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function paymentLink(Request $request, $id)
    {
        try {
            $ticket = Ticket::where('user_id', $request->user()->id)->find($id);

            if (!$ticket) {
                return response()->json([
                    'status' => false,
                    'message' => 'Ticket introuvable',
                ], 404);
            }

            if (!in_array($ticket->statut, ['completed', 'suspendu'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Le lien de paiement n\'est disponible que pour les tickets terminés ou suspendus.',
                ], 400);
            }

            $paymentUrl = "https://paiement.omeasupport.com/pay?ticket_id={$ticket->id}";

            return response()->json([
                'status' => true,
                'message' => 'Lien de paiement généré',
                'data' => [
                    'url' => $paymentUrl
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Erreur serveur lors de la génération du lien de paiement',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

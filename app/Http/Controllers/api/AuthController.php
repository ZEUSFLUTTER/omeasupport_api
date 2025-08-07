<?php

namespace App\Http\Controllers\Api; // Assurez-vous que le namespace est correct

use App\Http\Controllers\Controller;
use App\Models\User; // Assurez-vous d'importer votre modèle User
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Storage; // Pour la gestion des fichiers

class AuthController extends Controller
{
    // Connexion
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "email" => "required|email",
                "password" => "required",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => "Erreur de validation",
                    "errors" => $validator->errors(),
                ], 422);
            }

            // Tentative d'authentification
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    "status" => false,
                    "message" => "Email ou mot de passe incorrect",
                ], 401);
            }

            // Récupérer l'utilisateur authentifié
            $user = User::where('email', $request->email)->first();

            // Générer le token Sanctum
            $token = $user->createToken('auth_user')->plainTextToken;

            return response()->json([
                "status" => true,
                "message" => "Utilisateur connecté avec succès",
                "data" => [
                    "token" => $token,
                    "token_type" => "Bearer",
                    "role" => $user->role, 
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 500);
        }
    }

    // Inscription
    // Cette méthode est adaptée pour recevoir des données JSON ET potentiellement une image via FormData
    // Si la photo_profile est envoyée, le type de requête devrait être multipart/form-data
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "nom" => "required|string",
                "prenom" => "required|string",
                "email" => "required|email|unique:users,email",
                "password" => "required|confirmed", // 'confirmed' vérifie 'password_confirmation'
                "telephone" => "required|unique:users,telephone",
                "pays" => "required|string",
                "ville" => "required|string",
                "role" => "required|in:client,technician",
                "photo_profile" => "nullable|image|mimes:jpg,jpeg,png|max:2048", // Max 2MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => "Erreur de validation",
                    "errors" => $validator->errors(),
                ], 422);
            }

            $photoPath = null;
            if ($request->hasFile('photo_profile')) {
                // Stocke l'image dans storage/app/public/profile_photos
                $photoPath = $request->file('photo_profile')->store('profile_photos', 'public');
            }

            // Création de l'utilisateur
            $user = User::create([
                "nom" => $request->nom,
                "prenom" => $request->prenom,
                "email" => $request->email,
                "password" => Hash::make($request->password),
                "telephone" => $request->telephone,
                "pays" => $request->pays,
                "ville" => $request->ville,
                "role" => $request->role,
                "photo_profile" => $photoPath, // Enregistre le chemin relatif au dossier public
            ]);

            // Générer le token Sanctum pour l'utilisateur fraîchement créé
            $token = $user->createToken('auth_user')->plainTextToken;

            // Retourner la réponse avec le token
            // L'objet user complet sera récupéré via l'endpoint /api/auth/profile
            return response()->json([
                "status" => true,
                "message" => "Utilisateur créé avec succès",
                "data" => [
                    "token" => $token,
                    "token_type" => "Bearer",
                    "role" => $user->role, // Renvoi du rôle pour une vérification rapide
                ],
            ], 201); // 201 Created
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 500);
        }
    }

    public function profile(Request $request)
    {
        $user = $request->user(); // Récupère l'utilisateur authentifié via le token

        // Ajout de l'URL complète pour la photo de profil si elle existe
        if ($user->photo_profile) {
            $user->photo_profile_url = Storage::url($user->photo_profile);
        } else {
            $user->photo_profile_url = null;
        }

        // Retourne l'objet user complet
        return response()->json([
            "status" => true,
            "message" => "Profil utilisateur",
            "data" => $user, // Renvoi de l'objet User complet
        ]);
    }

    // Modifier son profil
    public function edit(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                "email" => "email|unique:users,email," . $user->id,
                "telephone" => "unique:users,telephone," . $user->id,
                "nom" => "string|nullable",
                "prenom" => "string|nullable",
                "pays" => "string|nullable",
                "ville" => "string|nullable",
                "photo_profile" => "nullable|image|mimes:jpg,jpeg,png|max:2048",
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => "Erreur de validation",
                    "errors" => $validator->errors(),
                ], 422);
            }

            $dataToUpdate = $request->except(['photo_profile']); // Exclure la photo_profile pour la gérer séparément

            if ($request->hasFile('photo_profile')) {
                // Supprimer l'ancienne photo si elle existe
                if ($user->photo_profile) {
                    Storage::disk('public')->delete($user->photo_profile);
                }
                // Stocker la nouvelle photo
                $dataToUpdate['photo_profile'] = $request->file('photo_profile')->store('profile_photos', 'public');
            }

            $user->update($dataToUpdate);

            // Recharger l'utilisateur pour inclure les dernières modifications et l'URL de la photo
            $user->refresh();
             if ($user->photo_profile) {
                $user->photo_profile_url = Storage::url($user->photo_profile);
            } else {
                $user->photo_profile_url = null;
            }

            return response()->json([
                "status" => true,
                "message" => "Profil mis à jour avec succès",
                "data" => $user, // Retourne l'utilisateur mis à jour
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 500);
        }
    }

    // Modifier le mot de passe
    public function updatePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "old_password" => "required",
                "new_password" => "required|confirmed|min:8", // Ajout de min:8 pour la sécurité
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => "Erreur de validation",
                    "errors" => $validator->errors(),
                ], 422);
            }

            if (!Hash::check($request->old_password, $request->user()->password)) {
                return response()->json([
                    "status" => false,
                    "message" => "Ancien mot de passe incorrect",
                ], 401);
            }

            $request->user()->update([
                "password" => Hash::make($request->new_password),
            ]);

            return response()->json([
                "status" => true,
                "message" => "Mot de passe modifié avec succès",
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 500);
        }
    }

    // Déconnexion
    public function logout(Request $request)
    {
        // Supprime le token d'accès personnel de l'utilisateur actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            "status" => true,
            "message" => "Déconnexion réussie",
        ]);
    }
}
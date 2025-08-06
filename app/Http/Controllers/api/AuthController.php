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

            // Retourner la réponse avec le token et les informations de base
            // L'objet user complet sera récupéré via l'endpoint /api/auth/profile
            return response()->json([
                "status" => true,
                "message" => "Utilisateur connecté avec succès",
                "data" => [
                    "token" => $token,
                    "token_type" => "Bearer",
                    "role" => $user->role, // Renvoi du rôle pour une vérification rapide côté client
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

    // 👤 Profil utilisateur connecté
    // Cet endpoint est appelé par Flutter pour récupérer les détails complets de l'utilisateur
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

    /**
     * Met à jour la photo de profil de l'utilisateur connecté
     */
    public function updateProfileImage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'photo_profile' => 'required|string', // Base64 string
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "status" => false,
                    "message" => "Erreur de validation",
                    "errors" => $validator->errors(),
                ], 422);
            }

            $user = $request->user();

            // Décoder l'image base64
            $imageData = base64_decode($request->photo_profile);

            // Valider que c'est bien une image
            $imageInfo = getimagesizefromstring($imageData);
            if (!$imageInfo) {
                return response()->json([
                    "status" => false,
                    "message" => "Le fichier fourni n'est pas une image valide.",
                ], 422);
            }

            // Vérifier le type MIME
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            if (!in_array($imageInfo['mime'], $allowedMimes)) {
                return response()->json([
                    "status" => false,
                    "message" => "Type d'image non supporté. Utilisez JPG, PNG ou GIF.",
                ], 422);
            }

            // Vérifier la taille (max 2MB en base64)
            if (strlen($request->photo_profile) > 2 * 1024 * 1024 * 4 / 3) { // 4/3 pour la conversion base64
                return response()->json([
                    "status" => false,
                    "message" => "L'image est trop volumineuse. Taille maximum: 2MB.",
                ], 422);
            }

            // Supprimer l'ancienne photo si elle existe
            if ($user->photo_profile) {
                Storage::disk('public')->delete($user->photo_profile);
            }

            // Générer un nom de fichier unique
            $extension = match ($imageInfo['mime']) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                default => 'jpg'
            };

            $filename = 'profile_photos/' . uniqid('profile_', true) . '.' . $extension;

            // Sauvegarder l'image
            Storage::disk('public')->put($filename, $imageData);

            // Mettre à jour l'utilisateur
            $user->update(['photo_profile' => $filename]);

            // Recharger l'utilisateur avec l'URL complète
            $user->refresh();
            $photoProfileUrl = $user->photo_profile ? Storage::url($user->photo_profile) : null;

            return response()->json([
                "status" => true,
                "message" => "Photo de profil mise à jour avec succès",
                "data" => [
                    "photo_profile_url" => $photoProfileUrl,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => "Erreur lors de la mise à jour de la photo de profil",
                "error" => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprime la photo de profil de l'utilisateur connecté
     */
    public function removeProfileImage(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->photo_profile) {
                return response()->json([
                    "status" => false,
                    "message" => "Aucune photo de profil à supprimer.",
                ], 400);
            }

            // Supprimer le fichier physique
            Storage::disk('public')->delete($user->photo_profile);

            // Mettre à jour l'utilisateur
            $user->update(['photo_profile' => null]);

            return response()->json([
                "status" => true,
                "message" => "Photo de profil supprimée avec succès",
                "data" => [
                    "photo_profile_url" => null,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => false,
                "message" => "Erreur lors de la suppression de la photo de profil",
                "error" => $th->getMessage(),
            ], 500);
        }
    }
}

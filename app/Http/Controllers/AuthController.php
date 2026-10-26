<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Créer un compte utilisateur.
     * Le nouveau compte reçoit automatiquement le rôle "client".
     */
    public function register(Request $request)
    {
        // Vérifier les données envoyées depuis Postman.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Rechercher le rôle client dans la base de données.
        $role = Role::where('name', 'client')->first();

        // Refuser la création si le rôle client n'existe pas.
        if (!$role) {
            return response()->json([
                'message' => 'Le rôle client doit être créé avant l’inscription.'
            ], 500);
        }

        // Créer le compte et protéger le mot de passe.
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
        ]);

        // Confirmer la création du compte.
        return response()->json([
            'message' => 'Compte créé avec succès.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->name,
            ],
        ], 201);
    }

    /**
     * Connecter un utilisateur et lui donner un token.
     */
    public function login(Request $request)
    {
        // Vérifier que l'adresse e-mail et le mot de passe sont fournis.
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Rechercher le compte correspondant à l'adresse e-mail.
        $user = User::where('email', $credentials['email'])->first();

        // Vérifier que le compte existe et que le mot de passe est correct.
        if (
            !$user ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            // Retourner une erreur si les identifiants sont incorrects.
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        // Vérifier que l'utilisateur possède un rôle.
        if (!$user->role) {
            return response()->json([
                'message' => 'Aucun rôle n’est attribué à ce compte.'
            ], 403);
        }

        // Créer un token personnel avec Laravel Sanctum.
        $token = $user->createToken('boutique-api')->plainTextToken;

        // Retourner le token et les informations utiles du compte.
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->name,
            ],
        ]);
    }

    /**
     * Déconnecter l'utilisateur en supprimant son token actuel.
     */
    public function logout(Request $request)
    {
        // Supprimer uniquement le token utilisé pour cette requête.
        $request->user()->currentAccessToken()->delete();

        // Confirmer la déconnexion.
        return response()->json([
            'message' => 'Déconnexion réussie.'
        ]);
    }
}

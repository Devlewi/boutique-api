
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AuthController;

// Inscription : accessible sans authentification.
// Limite : 5 tentatives par minute.
Route::post('/register', [
    AuthController::class,
    'register'
])->middleware('throttle:5,1');

// Connexion : accessible sans authentification.
// Limite : 5 tentatives par minute.
Route::post('/login', [
    AuthController::class,
    'login'
])->middleware('throttle:5,1');

// Routes protégées : un token Sanctum valide est obligatoire.
Route::middleware([
    'auth:sanctum',
    'throttle:60,1'
])->group(function () {

    // Déconnexion : supprimer le token utilisé.
    // Limite supplémentaire : 10 tentatives par minute.
    Route::post('/logout', [
        AuthController::class,
        'logout'
    ])->middleware('throttle:10,1');

    // Afficher les commandes autorisées pour l'utilisateur.
    Route::get('/orders', [
        OrderController::class,
        'index'
    ]);

    // Créer une commande.
    Route::post('/orders', [
        OrderController::class,
        'store'
    ]);

    // Afficher une commande précise.
    Route::get('/orders/{order}', [
        OrderController::class,
        'show'
    ]);

    // Annuler une commande selon les règles d'autorisation.
    Route::post('/orders/{order}/cancel', [
        OrderController::class,
        'cancel'
    ]);

    // Modifier le statut d'une commande.
    Route::patch('/orders/{order}/status', [
        OrderController::class,
        'updateStatus'
    ]);
});

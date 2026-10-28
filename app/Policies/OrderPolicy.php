<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

// Cette classe définit les droits d'accès aux commandes.
// Elle vérifie si un utilisateur peut consulter, créer,
// modifier le statut ou annuler une commande.
class OrderPolicy
{
    // Vérifie si l'utilisateur peut consulter la liste des commandes.
    public function viewAny(User $user)
    {
        // L'utilisateur doit pouvoir consulter ses propres commandes
        // ou avoir le droit de consulter toutes les commandes.
        return $user->hasPermission('orders.view-own')
            || $user->hasPermission('orders.view-all');
    }

    // Vérifie si l'utilisateur peut consulter une commande précise.
    public function view(User $user, Order $order)
    {
        // Un utilisateur autorisé à tout consulter
        // peut accéder à n'importe quelle commande.
        if ($user->hasPermission('orders.view-all')) {
            return true;
        }

        // Sinon, il doit avoir le droit de consulter ses commandes
        // et être le propriétaire de la commande demandée.
        return $user->hasPermission('orders.view-own')
            && $order->user_id === $user->id;
    }

    // Vérifie si l'utilisateur peut créer une commande.
    public function create(User $user)
    {
        // La création est autorisée uniquement si
        // l'utilisateur possède la permission correspondante.
        return $user->hasPermission('orders.create');
    }

    // Vérifie si l'utilisateur peut modifier le statut
    // d'une commande, par exemple de « pending » à « paid ».
    public function updateStatus(User $user, Order $order)
    {
        // L'utilisateur doit posséder cette permission.
        return $user->hasPermission('orders.update-status');
    }

    // Vérifie si l'utilisateur peut annuler une commande.
    public function cancel(User $user, Order $order)
    {
        // Une commande qui n'est plus en attente
        // ne peut pas être annulée par cette méthode.
        if ($order->status !== 'pending') {
            return false;
        }

        // Un utilisateur qui peut modifier les statuts
        // peut annuler toute commande encore en attente.
        if ($user->hasPermission('orders.update-status')) {
            return true;
        }

        // Sinon, l'utilisateur peut annuler sa propre commande
        // s'il possède la permission de consulter ses commandes.
        return $user->hasPermission('orders.view-own')
            && $user->id === $order->user_id;
    }
}
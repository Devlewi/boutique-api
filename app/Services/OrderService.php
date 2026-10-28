<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    // Crée une commande pour un utilisateur à partir des produits demandés.
    public function createOrder(
        User $user,
        array $items,
        ?string $idempotencyKey
    ): Order {
        // Regroupe les opérations dans une transaction :
        // en cas d'erreur, les modifications sont annulées.
        return DB::transaction(function () use (
            $user,
            $items,
            $idempotencyKey
        ) {
            // Vérifie si cette demande a déjà créé une commande
            // pour éviter de la créer une deuxième fois.
            if ($idempotencyKey !== null) {
                $existingOrder = Order::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                // Si la commande existe déjà, on la retourne.
                if ($existingOrder) {
                    return $existingOrder->load('items');
                }
            }

            // Calcule le montant total en centimes
            // et prépare les produits avant de créer la commande.
            $totalCents = 0;
            $preparedItems = [];

            // Vérifie chaque produit demandé.
            foreach ($items as $item) {
                // Verrouille le produit pendant la transaction
                // pour limiter les conflits liés au stock.
                $product = Product::whereKey($item['product_id'])
                    ->lockForUpdate()
                    ->first();

                // Refuse la commande si le produit n'existe pas
                // ou s'il n'est plus disponible à la vente.
                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Un produit est indisponible.'
                        ],
                    ]);
                }

                // Récupère la quantité demandée.
                $quantity = (int) $item['quantity'];

                // Vérifie que le stock permet de satisfaire la demande.
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Le stock est insuffisant pour un produit.'
                        ],
                    ]);
                }

                // Calcule le prix de cette ligne de commande.
                $lineTotal = $product->price_cents * $quantity;

                // Ajoute ce montant au total de la commande.
                $totalCents += $lineTotal;

                // Conserve les informations nécessaires
                // pour enregistrer les articles après validation.
                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price_cents' => $product->price_cents,
                ];
            }

            // Crée la commande avec son propriétaire,
            // son statut initial, son montant et sa clé d'idempotence.
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_cents' => $totalCents,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Enregistre les articles et retire les quantités du stock.
            foreach ($preparedItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                ]);

                // Diminue le stock du produit de la quantité commandée.
                $item['product']->decrement(
                    'stock',
                    $item['quantity']
                );
            }

            // Retourne la commande avec ses articles associés.
            return $order->load('items');
        });
    }
}

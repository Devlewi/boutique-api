<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createOrder(
        User $user,
        array $items,
        ?string $idempotencyKey
    ): Order {
        return DB::transaction(function () use (
            $user,
            $items,
            $idempotencyKey
        ) {
            if ($idempotencyKey !== null) {
                $existingOrder = Order::where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingOrder) {
                    return $existingOrder->load('items');
                }
            }

            $totalCents = 0;
            $preparedItems = [];

            foreach ($items as $item) {
                $product = Product::whereKey($item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product || !$product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Un produit est indisponible.'
                        ],
                    ]);
                }

                $quantity = (int) $item['quantity'];

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Le stock est insuffisant pour un produit.'
                        ],
                    ]);
                }

                $lineTotal = $product->price_cents * $quantity;

                $totalCents += $lineTotal;

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price_cents' => $product->price_cents,
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total_cents' => $totalCents,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($preparedItems as $item) {
                $order->items()->create([
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => $item['unit_price_cents'],
                ]);

                $item['product']->decrement(
                    'stock',
                    $item['quantity']
                );
            }

            return $order->load('items');
        });
    }
}

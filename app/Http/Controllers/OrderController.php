<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class OrderController extends Controller
{
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index()
    {
        Gate::authorize('viewAny', Order::class);

        /** @var User $user */
        $user = auth()->user();

        $query = Order::with('items');

        if (!$user->hasPermission('orders.view-all')) {
            $query->where('user_id', $user->id);
        }

        return response()->json(
            $query->latest()->paginate(20)
        );
    }

    public function store(StoreOrderRequest $request)
    {
        Gate::authorize('create', Order::class);

        $idempotencyKey = $request->header('Idempotency-Key');

        if (
            $idempotencyKey !== null
            && !preg_match('/^[A-Za-z0-9._-]{16,100}$/', $idempotencyKey)
        ) {
            return response()->json([
                'message' => 'La clé d’idempotence est invalide.'
            ], 422);
        }

        $order = $this->orderService->createOrder(
            $request->user(),
            $request->validated()['items'],
            $idempotencyKey
        );

        return response()->json([
            'message' => 'Commande enregistrée.',
            'data' => $order,
        ], 201);
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return response()->json([
            'data' => $order->load('items')
        ]);
    }

    public function cancel(Order $order)
    {
        Gate::authorize('cancel', $order);

        $order->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Commande annulée.'
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        Gate::authorize('updateStatus', $order);

        $validated = $request->validate([
            'status' => [
                'required',
                'in:processing,shipped,completed,cancelled',
            ],
        ]);

        $order->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => 'Statut de la commande mis à jour.',
            'data' => $order,
        ]);
    }
}

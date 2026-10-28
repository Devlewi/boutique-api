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
    // Service chargé de gérer la création des commandes.
    private $orderService;

    // Injecte le service pour pouvoir l'utiliser dans le contrôleur.
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    // Affiche la liste des commandes accessibles à l'utilisateur.
    public function index()
    {
        // Vérifie que l'utilisateur a le droit de consulter les commandes.
        Gate::authorize('viewAny', Order::class);

        /** @var User $user */
        $user = auth()->user();

        // Prépare la requête en chargeant aussi les articles des commandes.
        $query = Order::with('items');

        // Si l'utilisateur ne peut pas tout consulter,
        // affiche uniquement ses propres commandes.
        if (!$user->hasPermission('orders.view-all')) {
            $query->where('user_id', $user->id);
        }

        // Retourne les commandes les plus récentes,
        // avec une pagination de 20 résultats par page.
        return response()->json(
            $query->latest()->paginate(20)
        );
    }

    // Crée une nouvelle commande.
    public function store(StoreOrderRequest $request)
    {
        // Vérifie que l'utilisateur a le droit de créer une commande.
        Gate::authorize('create', Order::class);

        // Récupère la clé permettant d'éviter les commandes en double.
        $idempotencyKey = $request->header('Idempotency-Key');

        // Vérifie que la clé respecte le format et la longueur attendus.
        if (
            $idempotencyKey !== null
            && !preg_match('/^[A-Za-z0-9._-]{16,100}$/', $idempotencyKey)
        ) {
            // Retourne une erreur 422 si la clé est invalide.
            return response()->json([
                'message' => 'La clé d’idempotence est invalide.'
            ], 422);
        }

        // Délègue la création de la commande au service.
        // Les données ont déjà été validées par StoreOrderRequest.
        $order = $this->orderService->createOrder(
            $request->user(),
            $request->validated()['items'],
            $idempotencyKey
        );

        // Retourne la commande créée avec le statut HTTP 201.
        return response()->json([
            'message' => 'Commande enregistrée.',
            'data' => $order,
        ], 201);
    }

    // Affiche les détails d'une commande précise.
    public function show(Order $order)
    {
        // Vérifie que l'utilisateur est autorisé à consulter cette commande.
        Gate::authorize('view', $order);

        // Retourne la commande avec ses articles.
        return response()->json([
            'data' => $order->load('items')
        ]);
    }

    // Annule une commande.
    public function cancel(Order $order)
    {
        // Vérifie que l'utilisateur a le droit d'annuler cette commande.
        Gate::authorize('cancel', $order);

        // Modifie le statut de la commande.
        $order->update([
            'status' => 'cancelled'
        ]);

        // Confirme l'annulation.
        return response()->json([
            'message' => 'Commande annulée.'
        ]);
    }

    // Modifie le statut d'une commande.
    public function updateStatus(Request $request, Order $order)
    {
        // Vérifie que l'utilisateur peut modifier le statut.
        Gate::authorize('updateStatus', $order);

        // Vérifie que le statut envoyé fait partie des valeurs autorisées.
        $validated = $request->validate([
            'status' => [
                'required',
                'in:processing,shipped,completed,cancelled',
            ],
        ]);

        // Enregistre le nouveau statut.
        $order->update([
            'status' => $validated['status']
        ]);

        // Retourne un message et la commande mise à jour.
        return response()->json([
            'message' => 'Statut de la commande mis à jour.',
            'data' => $order,
        ]);
    }
}

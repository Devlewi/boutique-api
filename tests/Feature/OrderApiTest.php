<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    // Réinitialise la base de données pour chaque test.
    use RefreshDatabase;

    // Vérifie qu'un visiteur non connecté
    // ne peut pas consulter la liste des commandes.
    public function test_guest_cannot_list_orders()
    {
        $response = $this->getJson('/api/orders');

        // Une authentification est obligatoire.
        $response->assertStatus(401);
    }

    // Vérifie qu'un client ne peut pas consulter
    // la commande appartenant à un autre utilisateur.
    public function test_client_cannot_access_another_users_order()
    {
        // Crée le rôle du client.
        $clientRole = Role::create([
            'name' => 'client',
            'label' => 'Client',
        ]);

        // Crée le client qui effectue la demande.
        /** @var User $client */
        $client = User::factory()->create([
            'role_id' => $clientRole->id,
        ]);

        // Crée un autre utilisateur, propriétaire de la commande.
        $otherUser = User::factory()->create();

        // Crée directement une commande sans utiliser de factory.
        $order = Order::create([
            'user_id' => $otherUser->id,
            'status' => 'pending',
        ]);

        // Le client connecté tente de consulter cette commande.
        $response = $this->actingAs($client)
            ->getJson('/api/orders/' . $order->id);

        // Vérifie que la politique d'autorisation refuse l'accès.
        $response->assertStatus(403);
    }
}


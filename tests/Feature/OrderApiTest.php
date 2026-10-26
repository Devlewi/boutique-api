<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_orders()
    {
        $response = $this->getJson('/api/orders');

        $response->assertStatus(401);
    }

    public function test_client_cannot_access_admin_route()
    {
        $clientRole = Role::create([
            'name' => 'client',
            'label' => 'Client',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $clientRole->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }
}

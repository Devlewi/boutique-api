<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            'orders.create' => 'Créer une commande',
            'orders.view-own' => 'Consulter ses commandes',
            'orders.view-all' => 'Consulter toutes les commandes',
            'orders.update-status' => 'Modifier le statut des commandes',
            'products.manage' => 'Gérer les produits',
            'users.manage' => 'Gérer les utilisateurs',
            'roles.manage' => 'Gérer les rôles et permissions',
        ];

        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['label' => $label]
            );
        }

        $client = Role::firstOrCreate(
            ['name' => 'client'],
            ['label' => 'Client']
        );

        $manager = Role::firstOrCreate(
            ['name' => 'manager'],
            ['label' => 'Gestionnaire']
        );

        $admin = Role::firstOrCreate(
            ['name' => 'admin'],
            ['label' => 'Administrateur']
        );

        $client->permissions()->sync(
            Permission::whereIn('name', [
                'orders.create',
                'orders.view-own',
            ])->pluck('id')
        );

        $manager->permissions()->sync(
            Permission::whereIn('name', [
                'orders.view-all',
                'orders.update-status',
                'products.manage',
            ])->pluck('id')
        );

        $admin->permissions()->sync(
            Permission::pluck('id')
        );
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user)
    {
        return $user->hasPermission('orders.view-own')
            || $user->hasPermission('orders.view-all');
    }

    public function view(User $user, Order $order)
    {
        if ($user->hasPermission('orders.view-all')) {
            return true;
        }

        return $user->hasPermission('orders.view-own')
            && $order->user_id === $user->id;
    }

    public function create(User $user)
    {
        return $user->hasPermission('orders.create');
    }

    public function updateStatus(User $user, Order $order)
    {
        return $user->hasPermission('orders.update-status');
    }

    public function cancel(User $user, Order $order)
    {
        if ($order->status !== 'pending') {
            return false;
        }

        if ($user->hasPermission('orders.update-status')) {
            return true;
        }

        return $user->hasPermission('orders.view-own')
            && $user->id === $order->user_id;
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Purchase;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->tenant_id === $purchase->tenant_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'admin', 'manager', 'magasinier_gros']);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        if ($user->role === 'super_admin') return true;
        return $user->tenant_id === $purchase->tenant_id && 
            in_array($user->role, ['owner', 'admin', 'manager', 'gerant', 'magasinier_gros']);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->tenant_id === $purchase->tenant_id && $user->isAdmin();
    }
}

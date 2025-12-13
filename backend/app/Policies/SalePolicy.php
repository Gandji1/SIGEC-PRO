<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Sale;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->tenant_id === $sale->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->tenant_id === $sale->tenant_id && $user->isManager();
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->tenant_id === $sale->tenant_id && $user->isAdmin();
    }
}

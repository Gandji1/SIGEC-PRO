<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        if ($user->role === 'super_admin') return true;
        return $user->tenant_id === $supplier->tenant_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['super_admin', 'owner', 'admin', 'tenant', 'manager']);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        if ($user->role === 'super_admin') return true;
        return $user->tenant_id === $supplier->tenant_id && 
            in_array($user->role, ['owner', 'admin', 'tenant', 'manager']);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        if ($user->role === 'super_admin') return true;
        return $user->tenant_id === $supplier->tenant_id && 
            in_array($user->role, ['owner', 'admin', 'tenant']);
    }
}

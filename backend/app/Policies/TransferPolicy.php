<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $user->tenant_id === $transfer->tenant_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'admin', 'manager', 'magasinier_gros', 'magasinier_detail']);
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return $user->tenant_id === $transfer->tenant_id &&
            in_array($user->role, ['owner', 'admin', 'manager', 'magasinier_gros']);
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return $user->tenant_id === $transfer->tenant_id &&
            in_array($user->role, ['owner', 'admin', 'manager']);
    }
}

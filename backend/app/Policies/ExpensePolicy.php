<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    /**
     * Determine whether the user can view the expense
     */
    public function view(User $user, Expense $expense): bool
    {
        return $user->tenant_id === $expense->tenant_id;
    }

    /**
     * Determine whether the user can update the expense
     */
    public function update(User $user, Expense $expense): bool
    {
        return $user->tenant_id === $expense->tenant_id && 
               in_array($user->role, ['owner', 'manager', 'accountant']);
    }

    /**
     * Determine whether the user can delete the expense
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $user->tenant_id === $expense->tenant_id && 
               in_array($user->role, ['owner', 'manager', 'accountant']);
    }
}

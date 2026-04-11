<?php

namespace App\Policies;

use App\Models\AccountsReceivable;
use App\Models\User;

class AccountsReceivablePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function view(User $user, AccountsReceivable $accountsReceivable): bool
    {
        return $user->tenants()->whereKey($accountsReceivable->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    private function onboardedToCurrentTenant(User $user): bool
    {
        $tenantId = session('current_tenant_id');

        if ($tenantId === null) {
            return false;
        }

        return $user->tenants()->whereKey((int) $tenantId)->exists();
    }
}

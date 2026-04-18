<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->whereKey($tenant->getKey())->exists();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->whereKey($tenant->getKey())->exists();
    }
}

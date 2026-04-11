<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->tenants()->whereKey($customer->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->tenants()->whereKey($customer->tenant_id)->exists();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->tenants()->whereKey($customer->tenant_id)->exists();
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

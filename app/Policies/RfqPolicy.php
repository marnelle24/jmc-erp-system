<?php

namespace App\Policies;

use App\Models\Rfq;
use App\Models\User;

class RfqPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function view(User $user, Rfq $rfq): bool
    {
        return $user->tenants()->whereKey($rfq->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->onboardedToCurrentTenant($user);
    }

    public function update(User $user, Rfq $rfq): bool
    {
        return $user->tenants()->whereKey($rfq->tenant_id)->exists();
    }

    public function approve(User $user, Rfq $rfq): bool
    {
        return $this->update($user, $rfq);
    }

    public function delete(User $user, Rfq $rfq): bool
    {
        return $user->tenants()->whereKey($rfq->tenant_id)->exists();
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

<?php

namespace App\Domains\Tenancy\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganizationService
{
    public function execute(User $user, string $name): Tenant
    {
        return DB::transaction(function () use ($user, $name) {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlugFromName($name),
            ]);

            $user->tenants()->attach($tenant->id, ['role' => 'owner']);

            session(['current_tenant_id' => $tenant->id]);

            return $tenant;
        });
    }

    private function uniqueSlugFromName(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'organization';
        }

        $slug = $base;
        $suffix = 0;
        while (Tenant::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $base.'-'.Str::lower(Str::random(4));
            if ($suffix > 50) {
                $slug = $base.'-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $slug;
    }
}

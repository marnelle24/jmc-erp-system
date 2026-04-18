<?php

use App\Domains\Tenancy\Services\UpdateTenantOrganizationSettingsService;
use App\Http\Requests\UpdateTenantOrganizationSettingsRequest;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organization Settings')]
class extends Component {
    public string $base_currency = '';

    public function mount(): void
    {
        $tenantId = (int) session('current_tenant_id');
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        Gate::authorize('update', $tenant);

        $this->base_currency = $tenant->base_currency;
    }

    public function save(UpdateTenantOrganizationSettingsService $service): void
    {
        $tenantId = (int) session('current_tenant_id');
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        Gate::authorize('update', $tenant);

        $validated = $this->validate((new UpdateTenantOrganizationSettingsRequest)->rules());

        $service->execute($tenant, $validated);

        Flux::toast(variant: 'success', text: __('Organization settings saved.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Organization Settings') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Organization Settings')"
        :subheading="__('Base currency is used for amounts across procurement, accounting, and supplier views for this organization.')"
    >
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:select wire:model="base_currency" :label="__('Base currency (ISO 4217)')" required>
                @foreach (UpdateTenantOrganizationSettingsRequest::allowedCurrencies() as $code)
                    <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Preview:') }}
                <span class="font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ Number::currency(1234.56, in: $this->base_currency) }}</span>
            </flux:text>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="save-organization-settings">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>

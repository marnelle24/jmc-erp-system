<?php

use App\Domains\Tenancy\Services\CreateOrganizationService;
use App\Http\Requests\StoreOrganizationRequest;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::auth', ['title' => 'Create organization'])]
#[Title('Create organization')]
class extends Component {
    public string $name = '';

    public function mount(): void
    {
        if (Auth::user()->tenants()->exists()) {
            $this->redirect(route('dashboard', absolute: false), navigate: true);
        }
    }

    public function createOrganization(CreateOrganizationService $service): void
    {
        Gate::authorize('create', Tenant::class);

        $validated = $this->validate((new StoreOrganizationRequest)->rules());

        $service->execute(Auth::user(), $validated['name']);

        Flux::toast(variant: 'success', text: __('Organization created.'));

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Your organization')" :description="__('Name the business that will use this workspace. You can invite teammates later.')" />

    <form wire:submit="createOrganization" class="flex flex-col gap-6">
        <flux:input
            wire:model="name"
            :label="__('Organization name')"
            type="text"
            required
            autofocus
            :placeholder="__('e.g. Cebu Hardware Corp.')"
        />

        <flux:button variant="primary" type="submit" class="w-full">
            {{ __('Continue') }}
        </flux:button>
    </form>
</div>

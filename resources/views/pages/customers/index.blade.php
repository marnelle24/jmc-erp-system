<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Customers'])]
#[Title('Customers')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    #[On('customer-saved')]
    public function refreshAfterCustomerSave(): void
    {
        $this->resetPage();
    }

    public function openCustomerEditModal(int $customerId): void
    {
        $this->dispatch('customer-form-open-edit', customerId: $customerId);
    }

    public function getCustomersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(12)
            ->withPath(route('customers.index', absolute: false));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Customers Management') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your customers. Add, edit, and delete customers as needed.') }}</flux:text>
        </div>
        <livewire:customers.customer-form-modal />
    </div>

    <div class="min-w-0 w-full">
        <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <flux:heading size="lg">{{ __('Customers Master List') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('All customers recorded in the system. Contact details appear on sales documents.') }}</flux:text>
            </div>

            @if ($this->customers->isEmpty())
                <div class="p-6">
                    <flux:callout icon="users" color="zinc" inline :heading="__('No customers yet')" :text="__('Add a customer with the Add customer button above. Email and phone are optional.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Name') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Email') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Phone') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->customers as $customer)
                            <flux:table.row :key="$customer->id">
                                <flux:table.cell variant="strong" class="px-6!">{{ $customer->name }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($customer->email)
                                        <span class="text-zinc-700 dark:text-zinc-200">{{ $customer->email }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($customer->phone)
                                        <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $customer->phone }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        wire:click="openCustomerEditModal({{ $customer->id }})"
                                        inset="top bottom"
                                        class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        @if (! $this->customers->isEmpty() && $this->customers->hasPages())
            <div class="mt-4 flex justify-between px-1 sm:px-0 items-center gap-4">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 w-full">
                    {{ __('Showing') }} {{ $this->customers->firstItem() }} {{ __('to') }} {{ $this->customers->lastItem() }} {{ __('of') }} {{ $this->customers->total() }} {{ __('entries') }}
                </flux:text>
                {{ $this->customers->links('vendor.pagination.numbers-only') }}
            </div>
        @endif
    </div>
</div>

<?php

use App\Domains\Crm\Services\CreateSupplierService;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Suppliers'])]
#[Title('Suppliers')]
class extends Component {
    use WithPagination;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Supplier::class);
    }

    public function addSupplier(CreateSupplierService $service): void
    {
        Gate::authorize('create', Supplier::class);

        $validated = $this->validate((new StoreSupplierRequest)->rules());

        $service->execute((int) session('current_tenant_id'), $validated);

        $this->reset('name', 'email', 'phone', 'address');
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Supplier added.'));
    }

    public function getSuppliersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Supplier::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Suppliers') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Vendors you buy from. Used on RFQs and purchase orders.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-4">{{ __('Add supplier') }}</flux:heading>
        <form wire:submit="addSupplier" class="flex flex-col gap-4">
            <flux:input wire:model="name" :label="__('Name')" type="text" required />
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="email" :label="__('Email')" type="email" />
                <flux:input wire:model="phone" :label="__('Phone')" type="text" />
            </div>
            <flux:textarea wire:model="address" :label="__('Address')" rows="2" />
            <flux:button variant="primary" type="submit">{{ __('Save supplier') }}</flux:button>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Directory') }}</flux:heading>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Phone') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->suppliers as $supplier)
                        <tr wire:key="supplier-{{ $supplier->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $supplier->name }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $supplier->email ?? '—' }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $supplier->phone ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-zinc-500">{{ __('No suppliers yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->suppliers->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->suppliers->links() }}
            </div>
        @endif
    </div>
</div>

<?php

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Suppliers'])]
#[Title('Suppliers')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Supplier::class);
    }

    #[On('supplier-saved')]
    public function refreshAfterSupplierSave(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openSupplierEditModal(int $supplierId): void
    {
        $this->dispatch('supplier-form-open-edit', supplierId: $supplierId);
    }

    public function getSuppliersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Supplier::query()
            ->where('tenant_id', $tenantId)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhere('tax_id', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withPath(route('suppliers.index', absolute: false))
            ->withQueryString();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Suppliers Management') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your suppliers. Add, edit, and maintain vendor contact records.') }}</flux:text>
        </div>
        <livewire:suppliers.supplier-form-modal />
    </div>

    <div class="w-full min-w-0">
        <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Suppliers Master List') }}</flux:heading>
                        <flux:text class="mt-1 text-sm">{{ __('All suppliers recorded in the system. Contact details appear on procurement documents.') }}</flux:text>
                    </div>
                    <div class="w-full sm:max-w-xs shrink-0">
                        <flux:input
                            type="search"
                            wire:model.live.debounce.400ms="search"
                            placeholder="{{ __('Name, code, email, phone…') }}"
                        />
                    </div>
                </div>
            </div>

            @if ($this->suppliers->isEmpty())
                <div class="p-6">
                    @if (trim($this->search) !== '')
                        <flux:callout icon="magnifying-glass" color="zinc" inline :heading="__('No matching suppliers')" :text="__('Try another term or clear the search box.')" />
                    @else
                        <flux:callout icon="users" color="zinc" inline :heading="__('No suppliers yet')" :text="__('Add a supplier with the Add supplier button above. Email and phone are optional.')" />
                    @endif
                </div>
            @else
                <flux:table
                    :paginate="$this->suppliers->hasPages() ? $this->suppliers : null"
                    pagination:scroll-to
                >
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Name') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Code') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Email') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Phone') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->suppliers as $supplier)
                            <flux:table.row :key="$supplier->id">
                                <flux:table.cell variant="strong" class="px-6!">
                                    <a href="{{ route('suppliers.show', $supplier->id) }}" wire:navigate class="text-zinc-800 font-bold transition-all duration-300 hover:text-zinc-600 hover:underline dark:text-zinc-200">
                                        {{ $supplier->name }}
                                    </a>
                                </flux:table.cell>
                                <flux:table.cell class="px-6! font-mono text-sm">
                                    @if ($supplier->code)
                                        {{ $supplier->code }}
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">{{ $supplier->status->label() }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($supplier->email)
                                        <span class="text-zinc-700 dark:text-zinc-200">{{ $supplier->email }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($supplier->phone)
                                        <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $supplier->phone }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="flex justify-end gap-1 px-6!">
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        :href="route('suppliers.show', $supplier->id)"
                                        wire:navigate
                                        inset="top bottom"
                                        class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                    >
                                        {{ __('View') }}
                                    </flux:button>
                                    <flux:button
                                        type="button"
                                        size="xs"
                                        variant="primary"
                                        wire:click="openSupplierEditModal({{ $supplier->id }})"
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
    </div>
</div>

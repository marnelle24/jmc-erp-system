<?php

use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Inventory movements'])]
#[Title('Inventory movements')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', InventoryMovement::class);
    }

    public function getMovementsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->with('product')
            ->latest()
            ->paginate(20);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Inventory movements') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Authoritative ledger of stock changes. Operational documents post rows here in a transaction.') }}</flux:text>
        </div>
        <flux:button :href="route('inventory.adjustments.create')" variant="primary" wire:navigate>
            {{ __('Record adjustment') }}
        </flux:button>
    </div>

    <flux:card class="flex flex-col overflow-hidden p-0">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg">{{ __('Movement log') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Chronological entries with product, type, quantity, and notes.') }}</flux:text>
        </div>

        @if ($this->movements->isEmpty())
            <div class="p-6">
                <flux:callout icon="arrows-right-left" color="zinc" inline :heading="__('No movements yet')" :text="__('Record an adjustment or wait for procurement / sales posting.')" />
            </div>
        @else
            <flux:table
                :paginate="$this->movements->hasPages() ? $this->movements : null"
                pagination:scroll-to
            >
                <flux:table.columns sticky class="bg-white dark:bg-white/10">
                    <flux:table.column class="px-6!">{{ __('When') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Product') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end" class="px-6!">{{ __('Quantity') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Notes') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->movements as $movement)
                        <flux:table.row :key="$movement->id">
                            <flux:table.cell class="whitespace-nowrap px-6! text-zinc-600 dark:text-zinc-400">
                                {{ $movement->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </flux:table.cell>
                            <flux:table.cell variant="strong" class="px-6!">{{ $movement->product->name }}</flux:table.cell>
                            <flux:table.cell class="px-6!">
                                <flux:badge color="zinc" size="sm" inset="top bottom" class="capitalize">{{ $movement->movement_type->value }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="px-6!">
                                <span class="tabular-nums">{{ \Illuminate\Support\Number::format((float) $movement->quantity, maxPrecision: 4) }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate px-6! text-zinc-600 dark:text-zinc-400">
                                {{ $movement->notes ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>

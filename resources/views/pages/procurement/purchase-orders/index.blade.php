<?php

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Purchase orders'])]
#[Title('Purchase orders')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
    }

    public function getPurchaseOrdersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with('supplier')
            ->latest('order_date')
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Purchase Orders Management') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Committed buys from suppliers. Receive goods against an open order.') }}</flux:text>
        </div>
        <flux:button :href="route('procurement.purchase-orders.create')" variant="primary" wire:navigate>{{ __('New purchase order') }}</flux:button>
    </div>

    <div class="min-w-0 w-full">
        <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <flux:heading size="lg">{{ __('Purchase Order List') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('All purchase orders for your organization, including supplier, date, status, and quick access to details.') }}</flux:text>
            </div>

            @if ($this->purchaseOrders->isEmpty())
                <div class="p-6">
                    <flux:callout icon="document-text" color="zinc" inline :heading="__('No purchase orders yet')" :text="__('Create a purchase order to commit supplier buys and start receiving goods.')" />
                </div>
            @else
                <flux:table
                    :paginate="$this->purchaseOrders->hasPages() ? $this->purchaseOrders : null"
                    pagination:scroll-to
                >
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Reference') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Supplier') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Date') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->purchaseOrders as $po)
                            <flux:table.row :key="$po->id">
                                <flux:table.cell variant="strong" class="px-6!">{{ $po->reference_code }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="text-zinc-700 dark:text-zinc-200">{{ $po->supplier->name }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $po->order_date->format('Y-m-d') }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="text-zinc-700 dark:text-zinc-200">{{ str_replace('_', ' ', $po->status->value) }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        :href="route('procurement.purchase-orders.show', $po)"
                                        wire:navigate
                                        inset="top bottom"
                                        class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                    >
                                        {{ __('View') }}
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

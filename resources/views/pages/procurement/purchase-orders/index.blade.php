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

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Purchase orders') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Committed buys from suppliers. Receive goods against an open order.') }}</flux:text>
        </div>
        <flux:button :href="route('procurement.purchase-orders.create')" variant="primary" wire:navigate>{{ __('New purchase order') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('PO') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Date') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->purchaseOrders as $po)
                        <tr wire:key="po-{{ $po->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">#{{ $po->id }}</td>
                            <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $po->supplier->name }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $po->order_date->format('Y-m-d') }}</td>
                            <td class="px-6 py-3 capitalize text-zinc-700 dark:text-zinc-300">{{ str_replace('_', ' ', $po->status->value) }}</td>
                            <td class="px-6 py-3 text-end">
                                <flux:button size="sm" :href="route('procurement.purchase-orders.show', $po)" variant="ghost" wire:navigate>{{ __('View') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">{{ __('No purchase orders yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->purchaseOrders->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->purchaseOrders->links() }}
            </div>
        @endif
    </div>
</div>

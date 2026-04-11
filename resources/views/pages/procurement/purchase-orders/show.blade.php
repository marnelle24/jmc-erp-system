<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Purchase order'])]
#[Title('Purchase order')]
class extends Component {
    public PurchaseOrder $purchaseOrder;

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product'])
            ->findOrFail($id);

        Gate::authorize('view', $this->purchaseOrder);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Purchase order #:id', ['id' => $purchaseOrder->id]) }}</flux:heading>
            <flux:text class="mt-1">
                {{ $purchaseOrder->supplier->name }} · {{ $purchaseOrder->order_date->format('Y-m-d') }}
                · {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status->value)) }}
            </flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received], true))
                <flux:button variant="primary" :href="route('procurement.purchase-orders.receive', $purchaseOrder)" wire:navigate>{{ __('Receive goods') }}</flux:button>
            @endif
            <flux:button :href="route('procurement.purchase-orders.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
    </div>

    @if ($purchaseOrder->notes)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ $purchaseOrder->notes }}</flux:text>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Lines') }}</flux:heading>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Ordered') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Received') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($purchaseOrder->lines as $line)
                        @php
                            $received = $line->totalReceivedQuantity();
                            $remaining = bcsub((string) $line->quantity_ordered, $received, 4);
                        @endphp
                        <tr wire:key="pol-{{ $line->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $line->product->name }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $received, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $remaining, maxPrecision: 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

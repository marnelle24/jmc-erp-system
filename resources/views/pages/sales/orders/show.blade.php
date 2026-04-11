<?php

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Sales order'])]
#[Title('Sales order')]
class extends Component {
    public SalesOrder $salesOrder;

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->salesOrder = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'lines.product', 'invoices'])
            ->findOrFail($id);

        Gate::authorize('view', $this->salesOrder);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Sales order #:id', ['id' => $salesOrder->id]) }}</flux:heading>
            <flux:text class="mt-1">
                {{ $salesOrder->customer->name }} · {{ $salesOrder->order_date->format('Y-m-d') }}
                · {{ ucfirst(str_replace('_', ' ', $salesOrder->status->value)) }}
            </flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if (! in_array($salesOrder->status, [SalesOrderStatus::Cancelled, SalesOrderStatus::Fulfilled], true))
                <flux:button variant="primary" :href="route('sales.orders.ship', $salesOrder)" wire:navigate>{{ __('Ship / fulfill') }}</flux:button>
            @endif
            @if ($salesOrder->status !== SalesOrderStatus::Cancelled)
                <flux:button variant="outline" :href="route('sales.orders.invoice', $salesOrder)" wire:navigate>{{ __('Issue invoice') }}</flux:button>
            @endif
            <flux:button :href="route('sales.orders.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
    </div>

    @if ($salesOrder->notes)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ $salesOrder->notes }}</flux:text>
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
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Shipped') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Invoiced') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Ship remaining') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($salesOrder->lines as $line)
                        @php
                            $shipped = $line->totalShippedQuantity();
                            $invoiced = $line->totalInvoicedQuantity();
                            $shipRem = bcsub((string) $line->quantity_ordered, $shipped, 4);
                        @endphp
                        <tr wire:key="sol-{{ $line->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $line->product->name }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $shipped, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $invoiced, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $shipRem, maxPrecision: 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($salesOrder->invoices->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Invoices') }}</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Invoice') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Issued') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Customer ref.') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($salesOrder->invoices as $inv)
                            <tr wire:key="inv-{{ $inv->id }}">
                                <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">#{{ $inv->id }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $inv->issued_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $inv->customer_document_reference ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

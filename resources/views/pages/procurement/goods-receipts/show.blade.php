<?php

use App\Domains\Accounting\Services\PostAccountsPayableFromGoodsReceiptService;
use App\Domains\Procurement\Support\GoodsReceiptLineUnitCost;
use App\Http\Requests\PostAccountsPayableRequest;
use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryMovement;
use App\Support\TenantMoney;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Goods receipt'])]
#[Title('Goods receipt')]
class extends Component {
    public GoodsReceipt $goodsReceipt;

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->goodsReceipt = GoodsReceipt::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'purchaseOrder.supplier',
                'lines.purchaseOrderLine.product',
                'accountsPayable',
            ])
            ->findOrFail($id);

        Gate::authorize('view', $this->goodsReceipt);
    }

    public function postAccountsPayable(PostAccountsPayableFromGoodsReceiptService $service): void
    {
        Gate::authorize('create', AccountsPayable::class);

        $validated = Validator::make(
            ['goods_receipt_id' => $this->goodsReceipt->id],
            (new PostAccountsPayableRequest)->rules()
        )->validate();

        Gate::authorize('view', $this->goodsReceipt);

        try {
            $service->execute((int) session('current_tenant_id'), (int) $validated['goods_receipt_id']);
            Flux::toast(variant: 'success', text: __('Posted to accounts payable.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->goodsReceipt->refresh()->load([
            'purchaseOrder.supplier',
            'lines.purchaseOrderLine.product',
            'accountsPayable',
        ]);
    }

    public function documentTotalQuantity(): string
    {
        return $this->goodsReceipt->lines->reduce(
            fn (string $carry, GoodsReceiptLine $line): string => bcadd($carry, (string) $line->quantity_received, 4),
            '0'
        );
    }

    public function documentExtendedValue(): string
    {
        $sum = '0';
        foreach ($this->goodsReceipt->lines as $line) {
            $sum = bcadd($sum, GoodsReceiptLineUnitCost::extendedValue($line), 4);
        }

        return $sum;
    }

    /**
     * @return Collection<int, InventoryMovement>
     */
    public function getInventoryMovementsProperty(): Collection
    {
        $lineIds = $this->goodsReceipt->lines->pluck('id')->all();
        if ($lineIds === []) {
            return collect();
        }

        return InventoryMovement::query()
            ->where('tenant_id', $this->goodsReceipt->tenant_id)
            ->where('reference_type', GoodsReceiptLine::class)
            ->whereIn('reference_id', $lineIds)
            ->with('product')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}; ?>

@php
    $po = $goodsReceipt->purchaseOrder;
    $ap = $goodsReceipt->accountsPayable;
    $totalQty = $this->documentTotalQuantity();
    $extended = $this->documentExtendedValue();
    $apRemaining = $ap ? \App\Domains\Accounting\Support\OpenItemStatusResolver::remaining((string) $ap->total_amount, (string) $ap->amount_paid) : '0';
    $apBadgeColor = $ap ? match ($ap->status) {
        \App\Enums\AccountingOpenItemStatus::Open => 'amber',
        \App\Enums\AccountingOpenItemStatus::Partial => 'blue',
        default => 'green',
    } : 'zinc';
@endphp

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="xl">{{ __('Goods receipt') }} #{{ $goodsReceipt->id }}</flux:heading>
                @if ($goodsReceipt->status === \App\Enums\GoodsReceiptStatus::Posted)
                    <flux:badge color="green" size="sm">{{ __('Posted') }}</flux:badge>
                @else
                    <flux:badge color="zinc" size="sm">{{ __('Draft') }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1">{{ __('Document for purchase order :ref — inventory was updated when this receipt was posted.', ['ref' => $po->reference_code]) }}</flux:text>
            <div class="mt-3 flex flex-wrap gap-2">
                <flux:button size="sm" variant="outline" :href="route('procurement.purchase-orders.show', $po->id)" wire:navigate>
                    {{ __('View purchase order') }}
                </flux:button>
                <flux:button size="sm" variant="outline" :href="route('suppliers.show', $po->supplier_id)" wire:navigate>
                    {{ __('Supplier profile') }}
                </flux:button>
                <flux:button size="sm" variant="ghost" :href="route('procurement.goods-receipts.index')" wire:navigate>
                    {{ __('Back to register') }}
                </flux:button>
            </div>
        </div>
        <flux:card class="w-full max-w-md shrink-0 border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="md">{{ __('Receipt summary') }}</flux:heading>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Received at') }}</dt>
                    <dd class="text-end tabular-nums text-zinc-900 dark:text-zinc-100">{{ $goodsReceipt->received_at->translatedFormat('M j, Y — g:i A') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Total quantity') }}</dt>
                    <dd class="text-end tabular-nums font-medium text-zinc-900 dark:text-zinc-100">{{ \Illuminate\Support\Number::format((float) $totalQty, maxPrecision: 4) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Extended value') }}</dt>
                    <dd class="text-end tabular-nums font-medium text-zinc-900 dark:text-zinc-100">{{ TenantMoney::format((float) $extended, null, 2) }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">{{ __('Supplier invoice ref.') }}</dt>
                    <dd class="max-w-[12rem] truncate text-end text-zinc-900 dark:text-zinc-100">{{ $goodsReceipt->supplier_invoice_reference ?: '—' }}</dd>
                </div>
            </dl>
        </flux:card>
    </div>

    @if ($goodsReceipt->notes)
        <flux:card class="border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading size="md">{{ __('Notes') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-wrap text-sm text-zinc-700 dark:text-zinc-300">{{ $goodsReceipt->notes }}</flux:text>
        </flux:card>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="border border-zinc-200 p-5 dark:border-zinc-700 lg:col-span-2">
            <flux:heading size="lg">{{ __('Lines received') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Products, quantities, and unit costs as captured on posting (PO line cost used when line cost was left blank).') }}</flux:text>

            @if ($goodsReceipt->lines->isEmpty())
                <div class="mt-4">
                    <flux:callout color="zinc" icon="archive-box" inline :heading="__('No lines')" :text="__('This receipt has no line rows.')" />
                </div>
            @else
                <div class="mt-4 overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                            <tr>
                                <th class="px-4 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Qty') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Unit cost') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Extended') }}</th>
                                <th class="px-4 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($goodsReceipt->lines as $line)
                                @php
                                    $product = $line->purchaseOrderLine?->product;
                                    $uc = \App\Domains\Procurement\Support\GoodsReceiptLineUnitCost::resolvedUnitCost($line);
                                    $ext = \App\Domains\Procurement\Support\GoodsReceiptLineUnitCost::extendedValue($line);
                                @endphp
                                <tr wire:key="line-{{ $line->id }}">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $product?->name ?? __('Unknown product') }}</div>
                                        @if ($product)
                                            <div class="mt-0.5 font-mono text-xs text-zinc-500">{{ $product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity_received, maxPrecision: 4) }}</td>
                                    <td class="px-4 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ TenantMoney::format((float) $uc, null, 4) }}</td>
                                    <td class="px-4 py-3 text-end tabular-nums font-medium text-zinc-900 dark:text-zinc-100">{{ TenantMoney::format((float) $ext, null, 2) }}</td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($product)
                                            <flux:button size="xs" variant="ghost" :href="route('products.show', $product->id)" wire:navigate>{{ __('Stock card') }}</flux:button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </flux:card>

        <div class="flex flex-col gap-6">
            <flux:card class="border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Accounts payable') }}</flux:heading>
                @if ($ap)
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Invoice ref.') }}</dt>
                            <dd class="text-end text-zinc-900 dark:text-zinc-100">{{ $ap->invoice_number ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Total') }}</dt>
                            <dd class="text-end tabular-nums font-medium">{{ TenantMoney::format((float) $ap->total_amount, null, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Remaining') }}</dt>
                            <dd class="text-end tabular-nums">{{ TenantMoney::format((float) $apRemaining, null, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Status') }}</dt>
                            <dd class="text-end">
                                <flux:badge color="{{ $apBadgeColor }}" size="sm">
                                    {{ \Illuminate\Support\Str::headline($ap->status->value) }}
                                </flux:badge>
                            </dd>
                        </div>
                    </dl>
                    <flux:button class="mt-4 w-full" variant="outline" :href="route('accounting.payables.index')" wire:navigate>
                        {{ __('Open payables workspace') }}
                    </flux:button>
                @elseif ($goodsReceipt->status === \App\Enums\GoodsReceiptStatus::Posted && Gate::allows('create', \App\Models\AccountsPayable::class))
                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Create the vendor liability from this receipt using the same valuation as inventory (line cost with PO fallback).') }}
                    </flux:text>
                    <flux:button class="mt-4 w-full" variant="primary" wire:click="postAccountsPayable">
                        {{ __('Post accounts payable') }}
                    </flux:button>
                @else
                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('No payable posted for this receipt yet.') }}</flux:text>
                @endif
            </flux:card>
        </div>
    </div>

    <flux:card class="overflow-hidden border border-zinc-200 p-0 dark:border-zinc-700">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Inventory movements') }}</flux:heading>
            <flux:text class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Receipt-type movements created for each line when this document was posted.') }}</flux:text>
        </div>
        @if ($this->inventoryMovements->isEmpty())
            <div class="p-6">
                <flux:callout color="zinc" icon="archive-box" inline :heading="__('No movements')" :text="__('Movements appear after the receipt is posted with quantities.')" />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('When') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                            <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Qty') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->inventoryMovements as $m)
                            <tr wire:key="mov-{{ $m->id }}">
                                <td class="px-6 py-3 tabular-nums text-zinc-700 dark:text-zinc-300">{{ $m->created_at->translatedFormat('M j, Y g:i A') }}</td>
                                <td class="px-6 py-3">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $m->product?->name ?? '—' }}</span>
                                    @if ($m->product)
                                        <span class="ms-1 font-mono text-xs text-zinc-500">{{ $m->product->sku }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-end tabular-nums font-medium text-emerald-700 dark:text-emerald-300">+{{ \Illuminate\Support\Number::format((float) $m->quantity, maxPrecision: 4) }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $m->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:button variant="outline" size="sm" :href="route('inventory.movements.index')" wire:navigate>{{ __('Full movement ledger') }}</flux:button>
            </div>
        @endif
    </flux:card>
</div>

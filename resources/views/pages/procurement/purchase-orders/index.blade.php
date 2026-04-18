<?php

use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Purchase orders'])]
#[Title('Purchase orders')]
class extends Component {
    use WithPagination;

    public ?PurchaseOrder $receivePurchaseOrder = null;

    public string $received_at = '';

    public string $supplier_invoice_reference = '';

    public string $notes = '';

    /** @var array<int, string> purchase_order_line_id => quantity input */
    public array $receiveQty = [];

    /** @var array<int, string> purchase_order_line_id => actual unit cost input */
    public array $receivePrice = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $receiveId = (int) request()->query('receive', 0);
        if ($receiveId > 0 && Gate::allows('create', GoodsReceipt::class)) {
            $this->prepareReceiveModal($receiveId);
        }
    }

    public function prepareReceiveModal(int $purchaseOrderId): void
    {
        Gate::authorize('create', GoodsReceipt::class);

        $tenantId = (int) session('current_tenant_id');
        $po = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product'])
            ->find($purchaseOrderId);

        if (! $po) {
            Flux::toast(variant: 'danger', text: __('Purchase order not found.'));

            return;
        }

        if (in_array($po->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received], true)) {
            Flux::toast(variant: 'danger', text: __('This purchase order cannot receive goods in its current state.'));

            return;
        }

        $this->receivePurchaseOrder = $po;
        $this->received_at = now()->format('Y-m-d\TH:i');
        $this->supplier_invoice_reference = '';
        $this->notes = '';
        $this->receiveQty = [];
        $this->receivePrice = [];
        foreach ($this->receivePurchaseOrder->lines as $line) {
            $this->receiveQty[$line->id] = '';
            $this->receivePrice[$line->id] = $line->unit_cost !== null ? (string) $line->unit_cost : '';
        }

        $this->modal('receive-po')->show();
    }

    public function closeReceiveModal(): void
    {
        $this->receivePurchaseOrder = null;
        $this->reset('received_at', 'supplier_invoice_reference', 'notes', 'receiveQty', 'receivePrice');
        $this->modal('receive-po')->close();
    }

    public function postReceipt(PostGoodsReceiptService $service): void
    {
        Gate::authorize('create', GoodsReceipt::class);

        if (! $this->receivePurchaseOrder) {
            return;
        }

        $lines = [];
        foreach ($this->receivePurchaseOrder->lines as $line) {
            $raw = $this->receiveQty[$line->id] ?? '';
            $rawPrice = $this->receivePrice[$line->id] ?? '';
            $lines[] = [
                'purchase_order_line_id' => $line->id,
                'quantity_received' => $raw !== '' ? $raw : '0',
                'unit_cost' => $rawPrice !== '' ? $rawPrice : null,
            ];
        }

        $validated = Validator::make(
            [
                'received_at' => $this->received_at,
                'supplier_invoice_reference' => $this->supplier_invoice_reference,
                'notes' => $this->notes,
                'lines' => $lines,
            ],
            (new StoreGoodsReceiptRequest)->rules()
        )->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                $this->receivePurchaseOrder->id,
                $validated['lines'],
                $validated['received_at'],
                $validated['supplier_invoice_reference'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $poId = $this->receivePurchaseOrder->id;
        $this->receivePurchaseOrder = null;
        $this->reset('received_at', 'supplier_invoice_reference', 'notes', 'receiveQty', 'receivePrice');
        $this->modal('receive-po')->close();

        Flux::toast(variant: 'success', text: __('Goods receipt posted. Inventory movements recorded.'));

        $this->redirect(route('procurement.purchase-orders.show', ['id' => $poId], absolute: false), navigate: true);
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
                                <flux:table.cell variant="strong" class="px-6! font-mono tracking-wide font-bold">{{ $po->reference_code }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="bg-zinc-300 dark:bg-zinc-700/50 px-2 py-1 rounded-md text-xs! font-medium border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-200">{{ $po->supplier->name }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $po->order_date->translatedFormat('F j, Y - h:i A') }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @php
                                        $statusColor = match ($po->status->value) {
                                            PurchaseOrderStatus::Confirmed->value => 'bg-blue-100 text-blue-800 border-blue-200',
                                            PurchaseOrderStatus::PartiallyReceived->value => 'bg-yellow-200/80 text-yellow-800 border-yellow-400',
                                            PurchaseOrderStatus::Cancelled->value => 'bg-red-100 text-red-800 border-red-200',
                                            PurchaseOrderStatus::Received->value => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                        };
                                    @endphp
                                    <span class="capitalize {{ $statusColor }} px-2 py-1 rounded-md text-xs! font-medium border dark:border-zinc-700">{{ str_replace('_', ' ', $po->status->value) }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            size="xs"
                                            variant="outline"
                                            :href="route('procurement.purchase-orders.show', $po)"
                                            wire:navigate
                                            inset="top bottom"
                                            class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                        >
                                            {{ __('View') }}
                                        </flux:button>
                                        @if (Gate::allows('create', GoodsReceipt::class) && ! in_array($po->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received], true))
                                            <flux:button
                                                size="xs"
                                                variant="primary"
                                                wire:click="prepareReceiveModal({{ $po->id }})"
                                                inset="top bottom"
                                                class="cursor-pointer text-xs! p-1! px-2!"
                                            >
                                                {{ __('Receive') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>

    @if (Gate::allows('create', GoodsReceipt::class))
        <flux:modal name="receive-po" class="max-w-4xl" @close="closeReceiveModal">
            @if ($receivePurchaseOrder)
                <div class="flex max-h-[min(90vh,48rem)] flex-col gap-4 p-4">
                    <div class="shrink-0 mb-4">
                        <flux:heading size="lg">{{ __('Actualize Purchase Order :reference', ['reference' => $receivePurchaseOrder->reference_code]) }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Quantities post as receipt-type inventory movements in the same transaction as this document.') }}</flux:text>
                    </div>

                    <form wire:submit="postReceipt" class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pe-1">
                        <div class="grid grid-cols-2 gap-4">
                            <flux:input wire:model="received_at" :label="__('Received Date')" type="datetime-local" required />
    
                            <flux:input
                                wire:model="supplier_invoice_reference"
                                :label="__('Invoice # / Bill Ref. / Supplier DR #')"
                                type="text"
                                :placeholder="__('For accounts payable matching (optional)')"
                            />
                        </div>

                        <flux:textarea wire:model="notes" :label="__('Remarks / Notes')" rows="2" />

                        <div class="space-y-4">
                            <flux:heading size="md">{{ __('Purchase Order Items') }}</flux:heading>
                            @foreach ($receivePurchaseOrder->lines as $line)
                                @php
                                    $received = $line->totalReceivedQuantity();
                                    $remaining = bcsub((string) $line->quantity_ordered, $received, 4);
                                @endphp
                                <div wire:key="recv-{{ $line->id }}" class="grid items-start gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                                    <div>
                                        <flux:text class="font-bold text-zinc-900 dark:text-zinc-100 text-lg!">
                                            {{ $line->product->name }} <span class="font-mono text-xs text-zinc-500 dark:text-zinc-400">({{ $line->product->sku }})</span>
                                        </flux:text>
                                        <div class="flex flex-col gap-1">
                                            <flux:text class="text-xs text-zinc-500">
                                                {{ __('Ordered: :o ', [
                                                    'o' => \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4),
                                                ]) }}
                                            </flux:text>
                                            <flux:text class="text-xs text-zinc-500">
                                                {{ __('Already Received: :r ', [
                                                    'r' => \Illuminate\Support\Number::format((float) $received, maxPrecision: 4),
                                                ]) }}
                                            </flux:text>
                                            <flux:text class="text-xs text-zinc-500">
                                                {{ __('Remaining: :m ', [
                                                    'm' => \Illuminate\Support\Number::format((float) $remaining, maxPrecision: 4),
                                                ]) }}
                                            </flux:text>
                                        </div>
                                    </div>
                                    <div class="flex w-full gap-3 sm:max-w-xs sm:justify-self-end">
                                        <flux:input
                                            wire:model="receiveQty.{{ $line->id }}"
                                            :label="__('Actual Quantity')"
                                            type="text"
                                            inputmode="decimal"
                                            :placeholder="__('0')"
                                        />
                                        <flux:input
                                            wire:model="receivePrice.{{ $line->id }}"
                                            :label="__('Actual Price (PHP)')"
                                            type="text"
                                            inputmode="decimal"
                                            :placeholder="__('0')"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:button variant="primary" type="submit">{{ __('Post receipt') }}</flux:button>
                            <flux:button variant="ghost" type="button" wire:click="closeReceiveModal">{{ __('Cancel') }}</flux:button>
                        </div>
                    </form>
                </div>
            @endif
        </flux:modal>
    @endif
</div>

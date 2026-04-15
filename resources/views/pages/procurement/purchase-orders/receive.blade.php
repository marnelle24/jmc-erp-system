<?php

use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Receive goods'])]
#[Title('Receive goods')]
class extends Component {
    public PurchaseOrder $purchaseOrder;

    public string $received_at = '';

    public string $supplier_invoice_reference = '';

    public string $notes = '';

    /** @var array<int, string> purchase_order_line_id => quantity input */
    public array $receiveQty = [];

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product'])
            ->findOrFail($id);

        Gate::authorize('create', GoodsReceipt::class);

        $this->received_at = now()->format('Y-m-d\TH:i');

        foreach ($this->purchaseOrder->lines as $line) {
            $this->receiveQty[$line->id] = '';
        }
    }

    public function postReceipt(PostGoodsReceiptService $service): void
    {
        Gate::authorize('create', GoodsReceipt::class);

        $lines = [];
        foreach ($this->purchaseOrder->lines as $line) {
            $raw = $this->receiveQty[$line->id] ?? '';
            $lines[] = [
                'purchase_order_line_id' => $line->id,
                'quantity_received' => $raw !== '' ? $raw : '0',
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
                $this->purchaseOrder->id,
                $validated['lines'],
                $validated['received_at'],
                $validated['supplier_invoice_reference'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Goods receipt posted. Inventory movements recorded.'));

        $this->redirect(route('procurement.purchase-orders.show', $this->purchaseOrder, absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Receive against :reference', ['reference' => $purchaseOrder->reference_code]) }}</flux:heading>
        <flux:text class="mt-1">{{ __('Quantities post as receipt-type inventory movements in the same transaction as this document.') }}</flux:text>
    </div>

    <form wire:submit="postReceipt" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="received_at" :label="__('Received at')" type="datetime-local" required />

        <flux:input
            wire:model="supplier_invoice_reference"
            :label="__('Supplier invoice / bill reference')"
            type="text"
            :placeholder="__('For accounts payable matching (optional)')"
        />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Quantities') }}</flux:heading>
            @foreach ($purchaseOrder->lines as $line)
                @php
                    $received = $line->totalReceivedQuantity();
                    $remaining = bcsub((string) $line->quantity_ordered, $received, 4);
                @endphp
                <div wire:key="recv-{{ $line->id }}" class="grid gap-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                    <div>
                        <flux:text class="font-medium">{{ $line->product->name }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('Ordered: :o · Already received: :r · Remaining: :m', [
                                'o' => \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4),
                                'r' => \Illuminate\Support\Number::format((float) $received, maxPrecision: 4),
                                'm' => \Illuminate\Support\Number::format((float) $remaining, maxPrecision: 4),
                            ]) }}
                        </flux:text>
                    </div>
                    <flux:input
                        wire:model="receiveQty.{{ $line->id }}"
                        :label="__('Receive now')"
                        type="text"
                        inputmode="decimal"
                        :placeholder="__('0')"
                    />
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Post receipt') }}</flux:button>
            <flux:button :href="route('procurement.purchase-orders.show', $purchaseOrder)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

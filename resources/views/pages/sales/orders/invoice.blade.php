<?php

use App\Domains\Sales\Services\IssueSalesInvoiceService;
use App\Http\Requests\StoreSalesInvoiceRequest;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Issue invoice'])]
#[Title('Issue invoice')]
class extends Component {
    public SalesOrder $salesOrder;

    public string $issued_at = '';

    public string $customer_document_reference = '';

    public string $notes = '';

    /** @var array<int, string> sales_order_line_id => quantity */
    public array $invoiceQty = [];

    /** @var array<int, string> sales_order_line_id => unit price override */
    public array $unitPrice = [];

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->salesOrder = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'lines.product'])
            ->findOrFail($id);

        Gate::authorize('create', SalesInvoice::class);

        $this->issued_at = now()->format('Y-m-d\TH:i');

        foreach ($this->salesOrder->lines as $line) {
            $invoiced = $line->totalInvoicedQuantity();
            $remaining = bcsub((string) $line->quantity_ordered, $invoiced, 4);
            $this->invoiceQty[$line->id] = bccomp($remaining, '0', 4) === 1 ? $remaining : '';
            $this->unitPrice[$line->id] = $line->unit_price !== null ? (string) $line->unit_price : '';
        }
    }

    public function issueInvoice(IssueSalesInvoiceService $service): void
    {
        Gate::authorize('create', SalesInvoice::class);

        $lines = [];
        foreach ($this->salesOrder->lines as $line) {
            $qty = $this->invoiceQty[$line->id] ?? '';
            $price = $this->unitPrice[$line->id] ?? '';
            $lines[] = [
                'sales_order_line_id' => $line->id,
                'quantity_invoiced' => $qty !== '' ? $qty : '0',
                'unit_price' => $price !== '' ? $price : null,
            ];
        }

        $validated = Validator::make(
            [
                'issued_at' => $this->issued_at,
                'customer_document_reference' => $this->customer_document_reference,
                'notes' => $this->notes,
                'lines' => $lines,
            ],
            (new StoreSalesInvoiceRequest)->rules()
        )->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                $this->salesOrder->id,
                $validated['lines'],
                $validated['issued_at'],
                $validated['customer_document_reference'] ?? null,
                $validated['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Invoice issued. Ready for AR posting.'));

        $this->redirect(route('sales.orders.show', $this->salesOrder, absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Invoice for sales order #:id', ['id' => $salesOrder->id]) }}</flux:heading>
        <flux:text class="mt-1">{{ __('Invoice quantities are capped by what was ordered on each line (cumulative across invoices). Shipped amounts are shown for reference. Links to this order for accounts receivable.') }}</flux:text>
    </div>

    <form wire:submit="issueInvoice" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="issued_at" :label="__('Issued at')" type="datetime-local" required />

        <flux:input
            wire:model="customer_document_reference"
            :label="__('Customer / PO reference')"
            type="text"
            :placeholder="__('For accounts receivable matching (optional)')"
        />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Lines') }}</flux:heading>
            @foreach ($salesOrder->lines as $line)
                @php
                    $shipped = $line->totalShippedQuantity();
                    $invoiced = $line->totalInvoicedQuantity();
                    $invRem = bcsub((string) $line->quantity_ordered, $invoiced, 4);
                @endphp
                <div wire:key="inv-line-{{ $line->id }}" class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <flux:text class="font-medium">{{ $line->product->name }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('Ordered: :o · Shipped: :s · Invoiced: :i · Remaining to invoice: :r', [
                                'o' => \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4),
                                's' => \Illuminate\Support\Number::format((float) $shipped, maxPrecision: 4),
                                'i' => \Illuminate\Support\Number::format((float) $invoiced, maxPrecision: 4),
                                'r' => \Illuminate\Support\Number::format((float) $invRem, maxPrecision: 4),
                            ]) }}
                        </flux:text>
                    </div>
                    <flux:input
                        wire:model="invoiceQty.{{ $line->id }}"
                        :label="__('Invoice now')"
                        type="text"
                        inputmode="decimal"
                        :placeholder="__('0')"
                    />
                    <flux:input
                        wire:model="unitPrice.{{ $line->id }}"
                        :label="__('Unit price')"
                        type="text"
                        inputmode="decimal"
                    />
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Issue invoice') }}</flux:button>
            <flux:button :href="route('sales.orders.show', $salesOrder)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

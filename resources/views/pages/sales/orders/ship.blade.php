<?php

use App\Domains\Sales\Services\PostSalesShipmentService;
use App\Http\Requests\StoreSalesShipmentRequest;
use App\Models\SalesOrder;
use App\Models\SalesShipment;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Ship order'])]
#[Title('Ship order')]
class extends Component {
    public SalesOrder $salesOrder;

    public string $shipped_at = '';

    public string $notes = '';

    /** @var array<int, string> sales_order_line_id => quantity input */
    public array $shipQty = [];

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->salesOrder = SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'lines.product'])
            ->findOrFail($id);

        Gate::authorize('create', SalesShipment::class);

        $this->shipped_at = now()->format('Y-m-d\TH:i');

        foreach ($this->salesOrder->lines as $line) {
            $this->shipQty[$line->id] = '';
        }
    }

    public function postShipment(PostSalesShipmentService $service): void
    {
        Gate::authorize('create', SalesShipment::class);

        $lines = [];
        foreach ($this->salesOrder->lines as $line) {
            $raw = $this->shipQty[$line->id] ?? '';
            $lines[] = [
                'sales_order_line_id' => $line->id,
                'quantity_shipped' => $raw !== '' ? $raw : '0',
            ];
        }

        $validated = Validator::make(
            [
                'shipped_at' => $this->shipped_at,
                'notes' => $this->notes,
                'lines' => $lines,
            ],
            (new StoreSalesShipmentRequest)->rules()
        )->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                $this->salesOrder->id,
                $validated['lines'],
                $validated['shipped_at'],
                $validated['notes'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Shipment posted. Inventory issue movements recorded.'));

        $this->redirect(route('sales.orders.show', $this->salesOrder, absolute: false), navigate: true);
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Ship sales order #:id', ['id' => $salesOrder->id]) }}</flux:heading>
        <flux:text class="mt-1">{{ __('Quantities post as issue-type inventory movements in the same transaction as this shipment.') }}</flux:text>
    </div>

    <form wire:submit="postShipment" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="shipped_at" :label="__('Shipped at')" type="datetime-local" required />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Quantities') }}</flux:heading>
            @foreach ($salesOrder->lines as $line)
                @php
                    $shipped = $line->totalShippedQuantity();
                    $remaining = bcsub((string) $line->quantity_ordered, $shipped, 4);
                @endphp
                <div wire:key="ship-{{ $line->id }}" class="grid gap-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-2">
                    <div>
                        <flux:text class="font-medium">{{ $line->product->name }}</flux:text>
                        <flux:text class="text-sm text-zinc-500">
                            {{ __('Ordered: :o · Already shipped: :s · Remaining: :m', [
                                'o' => \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4),
                                's' => \Illuminate\Support\Number::format((float) $shipped, maxPrecision: 4),
                                'm' => \Illuminate\Support\Number::format((float) $remaining, maxPrecision: 4),
                            ]) }}
                        </flux:text>
                    </div>
                    <flux:input
                        wire:model="shipQty.{{ $line->id }}"
                        :label="__('Ship now')"
                        type="text"
                        inputmode="decimal"
                        :placeholder="__('0')"
                    />
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Post shipment') }}</flux:button>
            <flux:button :href="route('sales.orders.show', $salesOrder)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

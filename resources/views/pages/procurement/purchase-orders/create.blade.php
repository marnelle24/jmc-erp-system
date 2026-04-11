<?php

use App\Domains\Procurement\Services\CreatePurchaseOrderService;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\Product;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'New purchase order'])]
#[Title('New purchase order')]
class extends Component {
    public string $supplier_id = '';

    public string $order_date = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity_ordered: string, unit_cost: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', \App\Models\PurchaseOrder::class);
        $this->order_date = now()->toDateString();
        $this->lines = [
            ['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity_ordered' => '', 'unit_cost' => '']];
        }
    }

    public function save(CreatePurchaseOrderService $service): void
    {
        Gate::authorize('create', \App\Models\PurchaseOrder::class);

        $validated = $this->validate((new StorePurchaseOrderRequest)->rules());

        $po = $service->execute((int) session('current_tenant_id'), $validated);

        Flux::toast(variant: 'success', text: __('Purchase order created.'));

        $this->redirect(route('procurement.purchase-orders.show', $po, absolute: false), navigate: true);
    }

    public function getSuppliersProperty()
    {
        return Supplier::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->orderBy('name')
            ->get();
    }

    public function getProductsProperty()
    {
        return Product::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('New purchase order') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Commit quantities and costs. Receiving will post inventory movements against these lines.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:select wire:model="supplier_id" :label="__('Supplier')" :placeholder="__('Choose…')" required>
            @foreach ($this->suppliers as $supplier)
                <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="order_date" :label="__('Order date')" type="date" required />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Lines') }}</flux:heading>
                <flux:button type="button" wire:click="addLine" variant="ghost" size="sm">{{ __('Add line') }}</flux:button>
            </div>

            @foreach ($lines as $index => $line)
                <div wire:key="po-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-5">
                        <flux:select wire:model="lines.{{ $index }}.product_id" :label="__('Product')" :placeholder="__('Choose…')" required>
                            @foreach ($this->products as $product)
                                <flux:select.option :value="$product->id">{{ $product->name }} @if ($product->sku) ({{ $product->sku }}) @endif</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.quantity_ordered" :label="__('Qty ordered')" type="text" inputmode="decimal" required />
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.unit_cost" :label="__('Unit cost')" type="text" inputmode="decimal" />
                    </div>
                    <div class="md:col-span-2 flex justify-end pb-2 md:col-start-12">
                        <flux:button type="button" wire:click="removeLine({{ $index }})" variant="ghost" size="sm">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save purchase order') }}</flux:button>
            <flux:button :href="route('procurement.purchase-orders.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

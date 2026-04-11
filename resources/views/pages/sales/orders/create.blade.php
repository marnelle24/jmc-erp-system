<?php

use App\Domains\Sales\Services\CreateSalesOrderService;
use App\Http\Requests\StoreSalesOrderRequest;
use App\Models\Customer;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'New sales order'])]
#[Title('New sales order')]
class extends Component {
    public string $customer_id = '';

    public string $order_date = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity_ordered: string, unit_price: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', \App\Models\SalesOrder::class);
        $this->order_date = now()->toDateString();
        $this->lines = [
            ['product_id' => '', 'quantity_ordered' => '', 'unit_price' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity_ordered' => '', 'unit_price' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity_ordered' => '', 'unit_price' => '']];
        }
    }

    public function save(CreateSalesOrderService $service): void
    {
        Gate::authorize('create', \App\Models\SalesOrder::class);

        $validated = $this->validate((new StoreSalesOrderRequest)->rules());

        $order = $service->execute((int) session('current_tenant_id'), $validated);

        Flux::toast(variant: 'success', text: __('Sales order created.'));

        $this->redirect(route('sales.orders.show', $order, absolute: false), navigate: true);
    }

    public function getCustomersProperty()
    {
        return Customer::query()
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
        <flux:heading size="xl">{{ __('New sales order') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Commit quantities and prices. Fulfillment will post issue-type inventory movements against these lines.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:select wire:model="customer_id" :label="__('Customer')" :placeholder="__('Choose…')" required>
            @foreach ($this->customers as $customer)
                <flux:select.option :value="$customer->id">{{ $customer->name }}</flux:select.option>
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
                <div wire:key="so-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-12 md:items-end">
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
                        <flux:input wire:model="lines.{{ $index }}.unit_price" :label="__('Unit price')" type="text" inputmode="decimal" />
                    </div>
                    <div class="md:col-span-2 flex justify-end pb-2 md:col-start-12">
                        <flux:button type="button" wire:click="removeLine({{ $index }})" variant="ghost" size="sm">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save sales order') }}</flux:button>
            <flux:button :href="route('sales.orders.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

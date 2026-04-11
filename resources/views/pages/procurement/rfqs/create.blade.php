<?php

use App\Domains\Procurement\Services\CreateRfqService;
use App\Http\Requests\StoreRfqRequest;
use App\Models\Product;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'New RFQ'])]
#[Title('New RFQ')]
class extends Component {
    public string $supplier_id = '';

    public string $title = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: string, unit_price: string, notes: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', \App\Models\Rfq::class);
        $this->lines = [
            ['product_id' => '', 'quantity' => '', 'unit_price' => '', 'notes' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '', 'unit_price' => '', 'notes' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity' => '', 'unit_price' => '', 'notes' => '']];
        }
    }

    public function save(CreateRfqService $service): void
    {
        Gate::authorize('create', \App\Models\Rfq::class);

        $validated = $this->validate((new StoreRfqRequest)->rules());

        $service->execute((int) session('current_tenant_id'), $validated);

        Flux::toast(variant: 'success', text: __('RFQ created.'));

        $this->redirect(route('procurement.rfqs.index', absolute: false), navigate: true);
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
        <flux:heading size="xl">{{ __('New RFQ') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Request pricing from a supplier for one or more products.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:select wire:model="supplier_id" :label="__('Supplier')" :placeholder="__('Choose…')" required>
            @foreach ($this->suppliers as $supplier)
                <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="title" :label="__('Title')" type="text" :placeholder="__('Optional short label')" />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Line items') }}</flux:heading>
                <flux:button type="button" wire:click="addLine" variant="ghost" size="sm">{{ __('Add line') }}</flux:button>
            </div>

            @foreach ($lines as $index => $line)
                <div wire:key="rfq-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-4">
                        <flux:select wire:model="lines.{{ $index }}.product_id" :label="__('Product')" :placeholder="__('Choose…')" required>
                            @foreach ($this->products as $product)
                                <flux:select.option :value="$product->id">{{ $product->name }} @if ($product->sku) ({{ $product->sku }}) @endif</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.quantity" :label="__('Qty')" type="text" inputmode="decimal" required />
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.unit_price" :label="__('Est. unit price')" type="text" inputmode="decimal" />
                    </div>
                    <div class="md:col-span-3">
                        <flux:input wire:model="lines.{{ $index }}.notes" :label="__('Line notes')" type="text" />
                    </div>
                    <div class="md:col-span-1 flex justify-end pb-2">
                        <flux:button type="button" wire:click="removeLine({{ $index }})" variant="ghost" size="sm">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save RFQ') }}</flux:button>
            <flux:button :href="route('procurement.rfqs.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

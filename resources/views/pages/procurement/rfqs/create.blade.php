<?php

use App\Domains\Procurement\Services\CreateRfqService;
use App\Enums\RfqLineUnitType;
use App\Http\Requests\StoreRfqRequest;
use App\Models\Product;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Create Request For Quotation'])]
#[Title('Create Request For Quotation')]
class extends Component {
    public string $supplier_id = '';

    public string $title = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: string, unit_type: string, unit_price: string, notes: string}> */
    public array $lines = [];

    public function mount(): void
    {
        Gate::authorize('create', \App\Models\Rfq::class);
        $this->lines = [
            ['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => '']];
        }
    }

    public function save(CreateRfqService $service): void
    {
        Gate::authorize('create', \App\Models\Rfq::class);

        $validated = $this->validate((new StoreRfqRequest)->rules());

        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        $service->execute((int) session('current_tenant_id'), $validated, (int) $userId);

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

<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
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
                <div>
                    <flux:heading size="lg">{{ __('Product Items') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Optional. Can be updated from the purchase order when goods are received.') }}</flux:text>
                </div>
                <flux:button type="button" wire:click="addLine" variant="ghost" size="sm" class="flex-inline items-center gap-2 cursor-pointer bg-zinc-100 border border-zinc-200 dark:bg-zinc-700 dark:border-zinc-600 p-2">
                    {{ __('Add Product') }}
                </flux:button>
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
                    <div class="md:col-span-1">
                        <flux:input wire:model="lines.{{ $index }}.quantity" :label="__('Qty')" type="text" inputmode="decimal" required />
                    </div>
                    <div class="md:col-span-2">
                        <flux:select wire:model="lines.{{ $index }}.unit_type" :label="__('Unit type')" required>
                            @foreach (RfqLineUnitType::cases() as $unitType)
                                <flux:select.option :value="$unitType->value">{{ $unitType->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.unit_price" :label="__('Unit Price')" type="text" inputmode="decimal" />
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.notes" :label="__('Notes')" type="text" />
                    </div>
                    <div class="md:col-span-1 flex justify-end pb-3">
                        <flux:button type="button" wire:click="removeLine({{ $index }})" class="cursor-pointer dark:text-zinc-100" variant="ghost" size="xs">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Create Request For Quotation') }}</flux:button>
            <flux:button :href="route('procurement.rfqs.index')" class="cursor-pointer border border-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 dark:text-zinc-100" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

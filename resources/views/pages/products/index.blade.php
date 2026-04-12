<?php

use App\Domains\Inventory\Services\CreateProductService;
use App\Domains\Inventory\Services\UpdateProductService;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Products'])]
#[Title('Products')]
class extends Component {
    use WithPagination;

    public string $name = '';

    public string $sku = '';

    public string $description = '';

    public ?int $editingProductId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    public function startEdit(int $productId): void
    {
        $tenantId = (int) session('current_tenant_id');

        $product = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($productId)
            ->firstOrFail();

        Gate::authorize('update', $product);

        $this->editingProductId = $product->id;
        $this->name = $product->name;
        $this->sku = $product->sku ?? '';
        $this->description = $product->description ?? '';
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingProductId = null;
        $this->reset('name', 'sku', 'description');
        $this->resetValidation();
    }

    public function saveProduct(CreateProductService $create, UpdateProductService $update): void
    {
        $tenantId = (int) session('current_tenant_id');

        if ($this->editingProductId !== null) {
            $product = Product::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($this->editingProductId)
                ->firstOrFail();

            Gate::authorize('update', $product);

            $validated = $this->validate((new UpdateProductRequest)->rules());
            $update->execute($product, $validated);

            $this->cancelEdit();

            Flux::toast(variant: 'success', text: __('Product updated.'));

            return;
        }

        Gate::authorize('create', Product::class);

        $validated = $this->validate((new StoreProductRequest)->rules());

        $create->execute($tenantId, $validated);

        $this->reset('name', 'sku', 'description');
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Product added.'));
    }

    public function getProductsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Product::query()
            ->where('tenant_id', $tenantId)
            ->withSum('inventoryMovements', 'quantity')
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Products') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Items you stock. Quantities come only from inventory movements.') }}</flux:text>
    </div>

    {{-- Flex keeps the form column on the right; min-w-0 lets the table shrink inside the row. --}}
    <div class="flex w-full flex-col items-stretch gap-6 md:flex-row md:items-start">
        <div class="min-w-0 w-full basis-full md:basis-0 md:flex-[3]">
            <flux:card class="flex flex-col overflow-hidden p-0">
                <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                    <flux:heading size="lg">{{ __('Catalog') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('All products in this tenant, with on-hand from posted movements.') }}</flux:text>
                </div>

                @if ($this->products->isEmpty())
                    <div class="p-6">
                        <flux:callout icon="archive-box" color="zinc" inline :heading="__('No products yet')" :text="__('Add a product using the form beside this list. SKUs are optional but help when matching documents.')" />
                    </div>
                @else
                    <flux:table
                        :paginate="$this->products->hasPages() ? $this->products : null"
                        pagination:scroll-to
                        container:class="px-6"
                    >
                        <flux:table.columns sticky class="bg-white dark:bg-white/10">
                            <flux:table.column class="!px-4 !py-4">{{ __('Name') }}</flux:table.column>
                            <flux:table.column class="!px-4 !py-4">{{ __('SKU') }}</flux:table.column>
                            <flux:table.column align="end" class="!px-4 !py-4">{{ __('On hand') }}</flux:table.column>
                            <flux:table.column align="end" class="w-0 whitespace-nowrap !px-4 !py-4">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->products as $product)
                                <flux:table.row :key="$product->id">
                                    <flux:table.cell variant="strong" class="!px-4 !py-4">{{ $product->name }}</flux:table.cell>
                                    <flux:table.cell class="!px-4 !py-4">
                                        @if ($product->sku)
                                            <flux:badge color="zinc" size="sm" inset="top bottom">{{ $product->sku }}</flux:badge>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="!px-4 !py-4">
                                        <span class="tabular-nums">{{ number_format((float) ($product->inventory_movements_sum_quantity ?? 0), 2) }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="!px-4 !py-4">
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            wire:click="startEdit({{ $product->id }})"
                                            inset="top bottom"
                                        >
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>

        <aside class="w-full min-w-0 shrink-0 basis-full md:basis-0 md:flex-[1] md:sticky md:top-4 md:self-start">
            <flux:card size="sm">
                <flux:heading size="lg">{{ $this->editingProductId ? __('Edit product') : __('Add product') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($this->editingProductId)
                        {{ __('Update the name or description. SKU cannot be changed after creation.') }}
                    @else
                        {{ __('Name is required. Description is optional.') }}
                    @endif
                </flux:text>
                <flux:separator class="my-5" />
                <form wire:submit="saveProduct" class="flex flex-col gap-1">
                    <flux:fieldset>
                        <flux:input wire:model="name" :label="__('Name')" type="text" required />
                        <flux:input
                            wire:model="sku"
                            :label="__('SKU')"
                            type="text"
                            :disabled="(bool) $this->editingProductId"
                        />
                        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
                    </flux:fieldset>
                    <div class="mt-4 flex flex-col gap-2">
                        <flux:button variant="primary" type="submit" class="w-full">
                            {{ $this->editingProductId ? __('Update product') : __('Save product') }}
                        </flux:button>
                        @if ($this->editingProductId)
                            <flux:button type="button" variant="ghost" class="w-full" wire:click="cancelEdit">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            </flux:card>
        </aside>
    </div>
</div>

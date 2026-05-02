<?php

use App\Domains\Inventory\Services\CreateProductService;
use App\Domains\Inventory\Services\UpdateProductService;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
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

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    #[On('products-imported')]
    public function refreshAfterImport(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $nested) use ($term): void {
                    $nested->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->withSum('inventoryMovements', 'quantity')
            ->orderBy('name')
            ->paginate(12)
            ->withPath(route('products.index', absolute: false))
            ->withQueryString();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Products Management') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your products. Add, edit, and delete products as needed.') }}</flux:text>
        </div>
        <livewire:products.product-import-export-modal />
    </div>

    {{-- Flex keeps the form column on the right; min-w-0 lets the table shrink inside the row. --}}
    <div class="flex w-full flex-col items-stretch gap-6 md:flex-row md:items-start">
        <div class="min-w-0 w-full basis-full md:basis-0 md:flex-[3]">
            <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <flux:heading size="lg">{{ __('Products Master List') }}</flux:heading>
                            <flux:text class="mt-1 text-sm">{{ __('All products recorded in the system, with on-hand from posted movements.') }}</flux:text>
                        </div>
                        <div class="w-full sm:max-w-xs shrink-0">
                            <flux:input
                                type="search"
                                wire:model.live.debounce.400ms="search"
                                placeholder="{{ __('Name, SKU, or description…') }}"
                            />
                        </div>
                    </div>
                </div>

                @if ($this->products->isEmpty())
                    <div class="p-6">
                        @if (trim($this->search) !== '')
                            <flux:callout icon="magnifying-glass" color="zinc" inline :heading="__('No matching products')" :text="__('Try another term or clear the search box.')" />
                        @else
                            <flux:callout icon="archive-box" color="zinc" inline :heading="__('No products yet')" :text="__('Add a product using the form beside this list. SKUs are optional but help when matching documents.')" />
                        @endif
                    </div>
                @else
                    <flux:table>
                        <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                            <flux:table.column class="px-6!">{{ __('Name') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('SKU') }}</flux:table.column>
                            <flux:table.column align="end" class="px-6!">{{ __('On hand') }}</flux:table.column>
                            <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->products as $product)
                                <flux:table.row :key="$product->id" class="hover:bg-zinc-200/40 transition-all duration-300 dark:hover:bg-zinc-600/20">
                                    <flux:table.cell variant="strong" class="px-6!">
                                        {{ $product->name }}
                                        <flux:text class="text-zinc-500 text-xs">{{ $product->description }}</flux:text>
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6!">
                                        @if ($product->sku)
                                            <flux:badge color="zinc" size="sm" inset="top bottom">{{ $product->sku }}</flux:badge>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="px-6!">
                                        <span class="tabular-nums">{{ number_format((float) ($product->inventory_movements_sum_quantity ?? 0), 2) }}</span>
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="px-6!">
                                        <div class="flex items-center justify-end gap-1 text-right!">
                                            <flux:button
                                                :href="route('products.show', $product)"
                                                size="xs"
                                                variant="primary"
                                                wire:navigate
                                                inset="top bottom"
                                                class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                            >
                                                {{ __('View') }}
                                            </flux:button>
                                            <flux:button
                                                type="button"
                                                size="xs"
                                                variant="outline"
                                                wire:click="startEdit({{ $product->id }})"
                                                inset="top bottom"
                                                class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                            >
                                                {{ __('Edit') }}
                                            </flux:button>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            @if (! $this->products->isEmpty() && $this->products->hasPages())
                <div class="mt-4 flex justify-between px-1 sm:px-0 items-center gap-4">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 w-full">
                        {{ __('Showing') }} {{ $this->products->firstItem() }} {{ __('to') }} {{ $this->products->lastItem() }} {{ __('of') }} {{ $this->products->total() }} {{ __('entries') }}
                    </flux:text>
                    {{ $this->products->links('vendor.pagination.numbers-only') }}
                </div>
            @endif
        </div>

        <aside class="w-full min-w-0 shrink-0 basis-full md:basis-0 md:flex-[1] md:sticky md:top-4 md:self-start">
            <flux:card size="sm" class="bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
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
                        <flux:input wire:model="name" :label="__('Name')" placeholder="Product name" type="text" required />
                        <flux:input
                            wire:model="sku"
                            :label="__('SKU')"
                            placeholder="SKU / Product code"
                            type="text"
                            :disabled="(bool) $this->editingProductId"
                        />
                        <flux:textarea wire:model="description" :label="__('Description')" placeholder="About the product eg. Brand or additional details" rows="3" />
                    </flux:fieldset>
                    <div class="my-4 flex flex-col gap-2 space-y-2">
                        <flux:button variant="primary" type="submit" class="w-full cursor-pointer">
                            {{ $this->editingProductId ? __('Update product') : __('Save product') }}
                        </flux:button>
                        @if ($this->editingProductId)
                            <flux:button 
                                type="button" 
                                variant="filled" 
                                wire:click="cancelEdit"
                                inset="top bottom"
                                class="cursor-pointer text-xs! w-full border border-zinc-200 dark:border-white/40"
                            >
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            </flux:card>
        </aside>
    </div>
</div>

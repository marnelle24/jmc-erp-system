<?php

use App\Domains\Inventory\Services\CreateProductService;
use App\Http\Requests\StoreProductRequest;
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

    public function mount(): void
    {
        Gate::authorize('viewAny', Product::class);
    }

    public function addProduct(CreateProductService $service): void
    {
        Gate::authorize('create', Product::class);

        $validated = $this->validate((new StoreProductRequest)->rules());

        $tenantId = (int) session('current_tenant_id');
        $service->execute($tenantId, $validated);

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

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Products') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Items you stock. Quantities come only from inventory movements.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg" class="mb-4">{{ __('Add product') }}</flux:heading>
        <form wire:submit="addProduct" class="flex flex-col gap-4">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" type="text" required />
                <flux:input wire:model="sku" :label="__('SKU')" type="text" />
            </div>
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
            <flux:button variant="primary" type="submit">{{ __('Save product') }}</flux:button>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Catalog') }}</flux:heading>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Name') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('SKU') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('On hand') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->products as $product)
                        <tr wire:key="product-{{ $product->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $product->name }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $product->sku ?? '—' }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-900 dark:text-zinc-100">
                                {{ number_format((float) ($product->inventory_movements_sum_quantity ?? 0), 4) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-zinc-500">{{ __('No products yet. Add one above.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->products->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->products->links() }}
            </div>
        @endif
    </div>
</div>

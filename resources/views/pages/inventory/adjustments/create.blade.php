<?php

use App\Domains\Inventory\Services\PostInventoryMovementService;
use App\Enums\InventoryMovementType;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Inventory adjustment'])]
#[Title('Inventory adjustment')]
class extends Component {
    public string $product_id = '';

    public string $quantity = '';

    public string $notes = '';

    public function mount(): void
    {
        Gate::authorize('create', InventoryMovement::class);
    }

    public function postAdjustment(PostInventoryMovementService $service): void
    {
        Gate::authorize('create', InventoryMovement::class);

        $validated = $this->validate((new StoreInventoryAdjustmentRequest)->rules());

        $service->execute(
            (int) session('current_tenant_id'),
            (int) $validated['product_id'],
            (string) $validated['quantity'],
            InventoryMovementType::Adjustment,
            isset($validated['notes']) && $validated['notes'] !== '' ? $validated['notes'] : null,
        );

        $this->reset('product_id', 'quantity', 'notes');
        $this->product_id = '';

        Flux::toast(variant: 'success', text: __('Adjustment recorded.'));

        $this->redirect(route('inventory.movements.index', absolute: false), navigate: true);
    }

    public function getProductsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Product::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Inventory adjustment') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('Positive quantity increases on-hand stock; negative decreases it. This only creates a movement row—no direct quantity field on products.') }}
        </flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="postAdjustment" class="flex flex-col gap-6">
            <flux:select
                wire:model="product_id"
                :label="__('Product')"
                :placeholder="__('Choose a product…')"
                required
            >
                @foreach ($this->products as $product)
                    <flux:select.option :value="$product->id">{{ $product->name }} @if ($product->sku) ({{ $product->sku }}) @endif</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="quantity"
                :label="__('Quantity')"
                type="text"
                inputmode="decimal"
                required
                :placeholder="__('e.g. 10 or -2.5')"
            />

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" :placeholder="__('Reason or reference (optional)')" />

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Post adjustment') }}</flux:button>
                <flux:button :href="route('inventory.movements.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>

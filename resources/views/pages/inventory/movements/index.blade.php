<?php

use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Inventory movements'])]
#[Title('Inventory movements')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', InventoryMovement::class);
    }

    public function getMovementsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return InventoryMovement::query()
            ->where('tenant_id', $tenantId)
            ->with('product')
            ->latest()
            ->paginate(20);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Inventory movements') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Authoritative ledger of stock changes. Operational documents post rows here in a transaction.') }}</flux:text>
        </div>
        <flux:button :href="route('inventory.adjustments.create')" variant="primary" wire:navigate>
            {{ __('Record adjustment') }}
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('When') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Type') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Quantity') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->movements as $movement)
                        <tr wire:key="movement-{{ $movement->id }}">
                            <td class="whitespace-nowrap px-6 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $movement->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $movement->product->name }}
                            </td>
                            <td class="px-6 py-3 capitalize text-zinc-700 dark:text-zinc-300">
                                {{ $movement->movement_type->value }}
                            </td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-900 dark:text-zinc-100">
                                {{ \Illuminate\Support\Number::format((float) $movement->quantity, maxPrecision: 4) }}
                            </td>
                            <td class="max-w-xs truncate px-6 py-3 text-zinc-600 dark:text-zinc-400">
                                {{ $movement->notes ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">
                                {{ __('No movements yet. Record an adjustment or wait for procurement / sales posting.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->movements->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->movements->links() }}
            </div>
        @endif
    </div>
</div>

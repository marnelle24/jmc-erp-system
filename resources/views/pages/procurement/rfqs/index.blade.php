<?php

use App\Models\Rfq;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Requests for quotation'])]
#[Title('Requests for quotation')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', Rfq::class);
    }

    public function getRfqsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Rfq::query()
            ->where('tenant_id', $tenantId)
            ->with('supplier')
            ->latest()
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Requests for quotation (RFQ)') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Sourcing step before committing to a purchase order.') }}</flux:text>
        </div>
        <flux:button :href="route('procurement.rfqs.create')" variant="primary" wire:navigate>{{ __('New RFQ') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('ID') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Created') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->rfqs as $rfq)
                        <tr wire:key="rfq-{{ $rfq->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">#{{ $rfq->id }}</td>
                            <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $rfq->supplier->name }}</td>
                            <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $rfq->status->label() }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $rfq->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                            <td class="px-6 py-3 text-end">
                                <flux:button size="sm" :href="route('procurement.rfqs.show', $rfq)" variant="ghost" wire:navigate>{{ __('View') }}</flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">{{ __('No RFQs yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->rfqs->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->rfqs->links() }}
            </div>
        @endif
    </div>
</div>

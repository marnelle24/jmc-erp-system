<?php

use App\Models\AccountsReceivable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Accounts receivable'])]
#[Title('Accounts receivable')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', AccountsReceivable::class);
    }

    public function getReceivablesProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return AccountsReceivable::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'salesInvoice'])
            ->latest('posted_at')
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Accounts receivable') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Balances from issued sales invoices. Record customer payments to clear open items.') }}</flux:text>
        </div>
        <flux:button :href="route('accounting.customer-payments.create')" variant="primary" wire:navigate>{{ __('Record customer payment') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('ID') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Customer') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Invoice') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Total') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Paid') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->receivables as $row)
                        <tr wire:key="ar-{{ $row->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">#{{ $row->id }}</td>
                            <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $row->customer->name }}</td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">#{{ $row->sales_invoice_id }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $row->total_amount, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $row->amount_paid, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 capitalize text-zinc-700 dark:text-zinc-300">{{ $row->status->value }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-zinc-500">{{ __('No accounts receivable yet. Issue a sales invoice to create AR.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->receivables->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->receivables->links() }}
            </div>
        @endif
    </div>
</div>

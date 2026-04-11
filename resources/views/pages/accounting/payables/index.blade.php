<?php

use App\Domains\Accounting\Services\PostAccountsPayableFromGoodsReceiptService;
use App\Enums\GoodsReceiptStatus;
use App\Http\Requests\PostAccountsPayableRequest;
use App\Models\AccountsPayable;
use App\Models\GoodsReceipt;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Accounts payable'])]
#[Title('Accounts payable')]
class extends Component {
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', AccountsPayable::class);
    }

    public function postPayable(int $goodsReceiptId, PostAccountsPayableFromGoodsReceiptService $service): void
    {
        Gate::authorize('create', AccountsPayable::class);

        $validated = Validator::make(
            ['goods_receipt_id' => $goodsReceiptId],
            (new PostAccountsPayableRequest)->rules()
        )->validate();

        $receipt = GoodsReceipt::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->whereKey((int) $validated['goods_receipt_id'])
            ->firstOrFail();

        Gate::authorize('view', $receipt);

        try {
            $service->execute((int) session('current_tenant_id'), (int) $validated['goods_receipt_id']);
            Flux::toast(variant: 'success', text: __('Posted to accounts payable.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }

        $this->resetPage();
    }

    public function getPayablesProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->with('supplier', 'goodsReceipt')
            ->latest('posted_at')
            ->paginate(15);
    }

    public function getPendingReceiptsProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return GoodsReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('status', GoodsReceiptStatus::Posted)
            ->whereDoesntHave('accountsPayable')
            ->with(['purchaseOrder.supplier'])
            ->latest('received_at')
            ->get();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Accounts payable') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Post liabilities from posted goods receipts, then record supplier payments against open payables.') }}</flux:text>
    </div>

    @if ($this->pendingReceipts->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Receipts ready for AP') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('These posted receipts are not yet posted to accounts payable.') }}</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                        <tr>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Receipt') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Supplier') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Received') }}</th>
                            <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Supplier invoice ref.') }}</th>
                            <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->pendingReceipts as $receipt)
                            <tr wire:key="gr-pend-{{ $receipt->id }}">
                                <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">#{{ $receipt->id }}</td>
                                <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $receipt->purchaseOrder->supplier->name }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $receipt->received_at->format('Y-m-d H:i') }}</td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-400">{{ $receipt->supplier_invoice_reference ?? '—' }}</td>
                                <td class="px-6 py-3 text-end">
                                    <flux:button size="sm" variant="primary" wire:click="postPayable({{ $receipt->id }})">
                                        {{ __('Post to AP') }}
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Open payables') }}</flux:heading>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('ID') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Supplier') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Total') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Paid') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->payables as $payable)
                        <tr wire:key="ap-{{ $payable->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">#{{ $payable->id }}</td>
                            <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $payable->supplier->name }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $payable->total_amount, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $payable->amount_paid, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 capitalize text-zinc-700 dark:text-zinc-300">{{ $payable->status->value }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-zinc-500">{{ __('No accounts payable yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->payables->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $this->payables->links() }}
            </div>
        @endif
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('accounting.supplier-payments.create')" variant="primary" wire:navigate>{{ __('Record supplier payment') }}</flux:button>
    </div>
</div>

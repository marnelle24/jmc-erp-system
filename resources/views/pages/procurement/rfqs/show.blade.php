<?php

use App\Domains\Procurement\Services\ApproveRfqService;
use App\Domains\Procurement\Services\CreatePurchaseOrderFromRfqService;
use App\Enums\RfqStatus;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'RFQ'])]
#[Title('RFQ')]
class extends Component {
    public Rfq $rfq;

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->rfq = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product', 'purchaseOrders', 'creator', 'approver'])
            ->findOrFail($id);

        Gate::authorize('view', $this->rfq);
    }

    public function createPurchaseOrder(CreatePurchaseOrderFromRfqService $service): void
    {
        Gate::authorize('create', PurchaseOrder::class);

        try {
            $po = $service->execute((int) session('current_tenant_id'), $this->rfq->id);
            Flux::toast(variant: 'success', text: __('Purchase order created.'));
            $this->redirect(route('procurement.purchase-orders.show', $po, absolute: false), navigate: true);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function approve(ApproveRfqService $service): void
    {
        Gate::authorize('approve', $this->rfq);

        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        try {
            $this->rfq = $service->execute($this->rfq, (int) $userId);
            Flux::toast(variant: 'success', text: __('RFQ approved.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $rfq->reference_code }}</flux:heading>
            <flux:text class="mt-1">{{ $rfq->supplier->name }} · {{ $rfq->status->label() }}</flux:text>
            <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Created by :name', ['name' => $rfq->creator?->name ?? '—']) }}
                @if ($rfq->approver)
                    · {{ __('Approved by :name', ['name' => $rfq->approver->name]) }}
                @endif
            </flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($rfq->status === RfqStatus::PendingForApproval && $rfq->approved_by === null)
                <flux:button type="button" variant="outline" wire:click="approve">{{ __('Approve') }}</flux:button>
            @endif
            @if (in_array($rfq->status, [RfqStatus::PendingForApproval, RfqStatus::ApprovedNoPo, RfqStatus::Sent], true) && $rfq->purchaseOrders->isEmpty())
                <flux:button :href="route('procurement.rfqs.edit', $rfq)" variant="outline" wire:navigate>{{ __('Edit') }}</flux:button>
            @endif
            @if (in_array($rfq->status, [RfqStatus::ApprovedNoPo, RfqStatus::Sent], true) && $rfq->purchaseOrders->isEmpty())
                <flux:button variant="primary" wire:click="createPurchaseOrder">{{ __('Create purchase order') }}</flux:button>
            @endif
            <flux:button :href="route('procurement.rfqs.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
    </div>

    @if ($rfq->title)
        <flux:text class="-mt-2 font-medium">{{ $rfq->title }}</flux:text>
    @endif

    @if ($rfq->notes)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ $rfq->notes }}</flux:text>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-6 py-4 dark:border-zinc-700">
            <flux:heading size="lg">{{ __('Lines') }}</flux:heading>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Product') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Qty') }}</th>
                        <th class="px-6 py-3 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Unit') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Est. unit price') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($rfq->lines as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $line->product->name }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-zinc-700 dark:text-zinc-300">{{ $line->unit_type->label() }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">
                                {{ $line->unit_price !== null ? \Illuminate\Support\Number::format((float) $line->unit_price, maxPrecision: 4) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($rfq->purchaseOrders->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="sm" class="mb-2">{{ __('Linked purchase orders') }}</flux:heading>
            <ul class="list-inside list-disc text-sm text-zinc-700 dark:text-zinc-300">
                @foreach ($rfq->purchaseOrders as $po)
                    <li>
                        <a href="{{ route('procurement.purchase-orders.show', $po) }}" class="text-blue-600 underline dark:text-blue-400" wire:navigate>PO #{{ $po->id }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

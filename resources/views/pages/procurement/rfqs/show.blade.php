<?php

use App\Domains\Procurement\Services\ApproveRfqService;
use App\Domains\Procurement\Services\CreatePurchaseOrderFromRfqService;
use App\Domains\Procurement\Services\MarkRfqAsSentService;
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

    public function getEstimatedTotalProperty(): float
    {
        $total = 0.0;
        foreach ($this->rfq->lines as $line) {
            if ($line->unit_price === null) {
                continue;
            }

            $total += (float) $line->quantity * (float) $line->unit_price;
        }

        return $total;
    }

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->rfq = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product', 'purchaseOrders', 'creator', 'approver', 'sender'])
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

    public function markAsSent(MarkRfqAsSentService $service): void
    {
        Gate::authorize('markAsSent', $this->rfq);

        $userId = auth()->id();
        if ($userId === null) {
            return;
        }

        try {
            $this->rfq = $service->execute($this->rfq, (int) $userId);
            Flux::toast(variant: 'success', text: __('RFQ marked as sent.'));
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $rfq->reference_code }}</flux:heading>
            <flux:text class="mt-1">{{ __('Supplier: :name', ['name' => $rfq->supplier->name]) }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($rfq->status === RfqStatus::PendingForApproval && $rfq->approved_by === null)
                <flux:button type="button" variant="outline" wire:click="approve">{{ __('Approve') }}</flux:button>
            @endif
            @if ($rfq->status === RfqStatus::ApprovedNoPo && $rfq->purchaseOrders->isEmpty())
                <flux:button type="button" variant="outline" wire:click="markAsSent">{{ __('Mark as sent') }}</flux:button>
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

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40 lg:col-span-2">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <flux:heading size="lg">{{ __('RFQ Summary') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Workflow and commercial context for this request.') }}</flux:text>
            </div>
            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:text>
                    <flux:text class="mt-1 font-medium">{{ $rfq->status->label() }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Created') }}</flux:text>
                    <flux:text class="mt-1 font-medium">{{ $rfq->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Created by') }}</flux:text>
                    <flux:text class="mt-1 font-medium">{{ $rfq->creator?->name ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Approved by') }}</flux:text>
                    <flux:text class="mt-1 font-medium">{{ $rfq->approver?->name ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sent by') }}</flux:text>
                    <flux:text class="mt-1 font-medium">{{ $rfq->sender?->name ?? '—' }}</flux:text>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Sent at') }}</flux:text>
                    <flux:text class="mt-1 font-medium">
                        {{ $rfq->sent_at ? $rfq->sent_at->timezone(config('app.timezone'))->format('Y-m-d H:i') : '—' }}
                    </flux:text>
                </div>
            </div>
            @if ($rfq->title || $rfq->notes)
                <div class="border-t border-zinc-200 px-6 py-4 dark:border-white/10">
                    @if ($rfq->title)
                        <flux:text class="font-medium">{{ $rfq->title }}</flux:text>
                    @endif
                    @if ($rfq->notes)
                        <flux:text class="mt-2 text-zinc-700 dark:text-zinc-300">{{ $rfq->notes }}</flux:text>
                    @endif
                </div>
            @endif
        </flux:card>

        <flux:card class="bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <flux:heading size="lg">{{ __('Commercial Snapshot') }}</flux:heading>
            <div class="mt-4 grid gap-4">
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Line items') }}</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ $rfq->lines->count() }}</flux:heading>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Estimated total') }}</flux:text>
                    <flux:heading size="lg" class="mt-1 tabular-nums">
                        {{ \Illuminate\Support\Number::format($this->estimatedTotal, maxPrecision: 2) }}
                    </flux:heading>
                </div>
                <div>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Linked POs') }}</flux:text>
                    <flux:heading size="lg" class="mt-1">{{ $rfq->purchaseOrders->count() }}</flux:heading>
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
        <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
            <flux:heading size="lg">{{ __('Requested Items') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Quantities and estimated unit prices for supplier quotation.') }}</flux:text>
        </div>
        <flux:table>
            <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                <flux:table.column class="px-6!">{{ __('Product') }}</flux:table.column>
                <flux:table.column align="end" class="px-6!">{{ __('Qty') }}</flux:table.column>
                <flux:table.column class="px-6!">{{ __('Unit') }}</flux:table.column>
                <flux:table.column align="end" class="px-6!">{{ __('Est. unit price') }}</flux:table.column>
                <flux:table.column align="end" class="px-6!">{{ __('Line total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($rfq->lines as $line)
                    <flux:table.row :key="$line->id">
                        <flux:table.cell variant="strong" class="px-6!">{{ $line->product->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="px-6! tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity, maxPrecision: 4) }}</flux:table.cell>
                        <flux:table.cell class="px-6!">{{ $line->unit_type->label() }}</flux:table.cell>
                        <flux:table.cell align="end" class="px-6! tabular-nums">
                            {{ $line->unit_price !== null ? \Illuminate\Support\Number::format((float) $line->unit_price, maxPrecision: 4) : '—' }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="px-6! tabular-nums">
                            @if ($line->unit_price !== null)
                                {{ \Illuminate\Support\Number::format((float) $line->quantity * (float) $line->unit_price, maxPrecision: 2) }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    @if ($rfq->purchaseOrders->isNotEmpty())
        <flux:card class="bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <flux:heading size="lg">{{ __('Linked purchase orders') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Downstream commitments generated from this RFQ.') }}</flux:text>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($rfq->purchaseOrders as $po)
                    <flux:button :href="route('procurement.purchase-orders.show', $po)" wire:navigate variant="ghost" size="sm" class="border border-zinc-200 dark:border-white/30">
                        {{ $po->reference_code }}
                    </flux:button>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>

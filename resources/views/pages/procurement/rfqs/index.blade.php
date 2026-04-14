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
            ->with(['supplier', 'creator', 'approver'])
            ->latest()
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Request For Quotations') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Create a request for quotations to initiate the procurement process.') }}</flux:text>
        </div>
        <flux:button :href="route('procurement.rfqs.create')" variant="primary" wire:navigate>{{ __('Create Request For Quotation') }}</flux:button>
    </div>

    <div class="min-w-0 w-full">
        <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
            <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                <flux:heading size="lg">{{ __('Request For Quotation List') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('All request for quotations for your organization. Creator, approver, supplier, status, and timestamps appear for each request.') }}</flux:text>
            </div>

            @if ($this->rfqs->isEmpty())
                <div class="p-6">
                    <flux:callout icon="document-text" color="zinc" inline :heading="__('No RFQs yet')" :text="__('Create a purchase request to start the procurement process.')" />
                </div>
            @else
                <flux:table
                    :paginate="$this->rfqs->hasPages() ? $this->rfqs : null"
                    pagination:scroll-to
                >
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Reference') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Supplier') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Created by') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Approved by') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Created') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->rfqs as $rfq)
                            <flux:table.row :key="$rfq->id">
                                <flux:table.cell variant="strong" class="px-6!">{{ $rfq->reference_code }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="text-zinc-700 dark:text-zinc-200">{{ $rfq->supplier->name }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="text-zinc-700 dark:text-zinc-200">{{ $rfq->status->label() }}</span>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($rfq->creator)
                                        <span class="text-zinc-700 dark:text-zinc-200">{{ $rfq->creator->name }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    @if ($rfq->approver)
                                        <span class="text-zinc-700 dark:text-zinc-200">{{ $rfq->approver->name }}</span>
                                    @else
                                        <flux:text class="text-zinc-400">—</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $rfq->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button
                                        size="xs"
                                        variant="ghost"
                                        :href="route('procurement.rfqs.show', $rfq)"
                                        wire:navigate
                                        inset="top bottom"
                                        class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                    >
                                        {{ __('View') }}
                                    </flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    </div>
</div>

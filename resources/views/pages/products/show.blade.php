<?php

use App\Domains\Inventory\DTOs\ProductActivityTimelineEntry;
use App\Domains\Inventory\DTOs\ProductChartSeries;
use App\Domains\Inventory\DTOs\ProductStockKpis;
use App\Domains\Inventory\Services\BuildProductActivityTimelineService;
use App\Domains\Inventory\Services\GetProductChartSeriesService;
use App\Domains\Inventory\Services\GetProductStockKpisService;
use App\Domains\Inventory\Services\ListInventoryMovementsForTenantService;
use App\Domains\Inventory\Services\ResolveInventoryMovementSourceLinkService;
use App\Domains\Inventory\Services\UpdateProductService;
use App\Models\InventoryMovement;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Product'])]
#[Title('Product')]
class extends Component {
    use WithPagination;

    public Product $product;

    public string $chart_date_from = '';

    public string $chart_date_to = '';

    public string $reorder_point_input = '';

    public string $reorder_qty_input = '';

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->product = Product::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        Gate::authorize('view', $this->product);

        $this->chart_date_from = now()->subDays(29)->toDateString();
        $this->chart_date_to = now()->toDateString();

        $this->reorder_point_input = $this->product->reorder_point !== null ? (string) $this->product->reorder_point : '';
        $this->reorder_qty_input = $this->product->reorder_qty !== null ? (string) $this->product->reorder_qty : '';
    }

    public function updatedChartDateFrom(): void
    {
        $this->resetPage('movementsPage');
    }

    public function updatedChartDateTo(): void
    {
        $this->resetPage('movementsPage');
    }

    public function applyChartPresetLast7Days(): void
    {
        $this->chart_date_from = now()->subDays(6)->toDateString();
        $this->chart_date_to = now()->toDateString();
        $this->resetPage('movementsPage');
    }

    public function applyChartPresetLast30Days(): void
    {
        $this->chart_date_from = now()->subDays(29)->toDateString();
        $this->chart_date_to = now()->toDateString();
        $this->resetPage('movementsPage');
    }

    public function applyChartPresetLast90Days(): void
    {
        $this->chart_date_from = now()->subDays(89)->toDateString();
        $this->chart_date_to = now()->toDateString();
        $this->resetPage('movementsPage');
    }

    public function applyChartPresetYearToDate(): void
    {
        $this->chart_date_from = now()->startOfYear()->toDateString();
        $this->chart_date_to = now()->toDateString();
        $this->resetPage('movementsPage');
    }

    public function saveReorder(UpdateProductService $update): void
    {
        Gate::authorize('update', $this->product);

        $validated = $this->validate([
            'reorder_point_input' => ['nullable', 'numeric', 'min:0'],
            'reorder_qty_input' => ['nullable', 'numeric', 'min:0'],
        ]);

        $update->execute($this->product, [
            'name' => $this->product->name,
            'description' => $this->product->description,
            'reorder_point' => $validated['reorder_point_input'] !== '' ? $validated['reorder_point_input'] : null,
            'reorder_qty' => $validated['reorder_qty_input'] !== '' ? $validated['reorder_qty_input'] : null,
        ]);

        $this->product->refresh();

        Flux::toast(variant: 'success', text: __('Reorder settings saved.'));
    }

    public function getKpisProperty(): ProductStockKpis
    {
        $tenantId = (int) session('current_tenant_id');

        return app(GetProductStockKpisService::class)->execute($tenantId, $this->product->id);
    }

    public function getChartSeriesProperty(): ProductChartSeries
    {
        $tenantId = (int) session('current_tenant_id');

        return app(GetProductChartSeriesService::class)->execute(
            $tenantId,
            $this->product->id,
            $this->chart_date_from,
            $this->chart_date_to,
        );
    }

    public function getChartSeriesJsonProperty(): string
    {
        $s = $this->chartSeries;

        return json_encode([
            'inventoryBalance' => $s->inventoryBalance,
            'purchaseUnitCost' => $s->purchaseUnitCost,
            'saleUnitPrice' => $s->saleUnitPrice,
        ], JSON_THROW_ON_ERROR);
    }

    public function getTimelineProperty(): array
    {
        $tenantId = (int) session('current_tenant_id');

        return app(BuildProductActivityTimelineService::class)->execute($tenantId, $this->product->id);
    }

    public function getMovementsProperty(): LengthAwarePaginator
    {
        $tenantId = (int) session('current_tenant_id');

        return app(ListInventoryMovementsForTenantService::class)
            ->query($tenantId, [
                'date_from' => $this->chart_date_from !== '' ? $this->chart_date_from : null,
                'date_to' => $this->chart_date_to !== '' ? $this->chart_date_to : null,
                'product_id' => $this->product->id,
                'sort' => 'created_at',
                'direction' => 'desc',
            ])
            ->paginate(15, ['*'], 'movementsPage')
            ->setPath(route('products.show', $this->product));
    }

    public function resolveMovementSource(InventoryMovement $movement): \App\Domains\Inventory\DTOs\InventoryMovementSourceLink
    {
        return app(ResolveInventoryMovementSourceLinkService::class)->resolve($movement);
    }

    public function isLowStock(): bool
    {
        if ($this->product->reorder_point === null) {
            return false;
        }

        $onHand = (float) $this->kpis->onHand;
        $threshold = (float) $this->product->reorder_point;

        return $onHand <= $threshold;
    }

    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'rfq' => __('RFQ'),
            'purchase_order' => __('Purchase'),
            'goods_receipt' => __('Receipt'),
            'inventory_movement' => __('Movement'),
            'sales_order' => __('Sales order'),
            'sales_shipment' => __('Shipment'),
            'sales_invoice' => __('Invoice'),
            default => $category,
        };
    }
}; ?>

@php
    /** @var ProductStockKpis $kpis */
    $kpis = $this->kpis;
@endphp

<div class="flex w-full max-w-6xl flex-1 flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $product->name }}</flux:heading>
            <flux:text class="mt-1">
                @if ($product->sku)
                    <flux:badge color="zinc" size="sm" inset="top bottom">{{ $product->sku }}</flux:badge>
                @endif
                @if ($product->description)
                    <span class="mt-2 block text-sm text-zinc-600 dark:text-zinc-400">{{ $product->description }}</span>
                @endif
            </flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('products.index')" variant="ghost" wire:navigate>
                <flux:icon name="arrow-left" class="w-4 h-4" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    @if ($this->isLowStock())
        <flux:callout icon="exclamation-triangle" color="amber" inline :heading="__('Below reorder level')" :text="__('On-hand quantity is at or below your minimum / reorder level. Consider creating a purchase order.')" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:card class="relative overflow-hidden border border-zinc-200/90 bg-linear-to-br from-white to-zinc-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-800">
            <div class="pointer-events-none absolute -right-8 -top-8 h-20 w-20 rounded-full bg-blue-500/10 blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('On hand') }}</flux:text>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ \Illuminate\Support\Number::format((float) $kpis->onHand, maxPrecision: 4) }}</p>
                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Current quantity available in stock ledger') }}</flux:text>
                </div>
                <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:bg-blue-400/20 dark:text-blue-300">
                    <span class="text-base font-semibold">#</span>
                </div>
            </div>
        </flux:card>
        <flux:card class="relative overflow-hidden border border-zinc-200/90 bg-linear-to-br from-white to-zinc-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-800">
            <div class="pointer-events-none absolute -right-8 -top-8 h-20 w-20 rounded-full bg-emerald-500/10 blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Incoming (open PO)') }}</flux:text>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ \Illuminate\Support\Number::format((float) $kpis->incoming, maxPrecision: 4) }}</p>
                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Expected stock from open purchase orders') }}</flux:text>
                </div>
                <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:bg-emerald-400/20 dark:text-emerald-300">
                    <span class="text-sm font-semibold">PO</span>
                </div>
            </div>
        </flux:card>
        <flux:card class="relative overflow-hidden border border-zinc-200/90 bg-linear-to-br from-white to-zinc-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-800">
            <div class="pointer-events-none absolute -right-8 -top-8 h-20 w-20 rounded-full bg-amber-500/10 blur-xl"></div>
            <div class="relative flex items-start justify-between">
                <div>
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Committed (open SO)') }}</flux:text>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-amber-700 dark:text-amber-400">{{ \Illuminate\Support\Number::format((float) $kpis->committed, maxPrecision: 4) }}</p>
                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Allocated quantity for open sales orders') }}</flux:text>
                </div>
                <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:bg-amber-400/20 dark:text-amber-300">
                    <span class="text-sm font-semibold">SO</span>
                </div>
            </div>
        </flux:card>
        <flux:card class="relative overflow-hidden border border-zinc-200/90 bg-linear-to-br from-white to-zinc-50 p-5 shadow-sm dark:border-zinc-700 dark:from-zinc-900 dark:to-zinc-800">
            <div class="pointer-events-none absolute -right-8 -top-8 h-20 w-20 rounded-full bg-violet-500/10 blur-xl"></div>
            <div class="relative">
                <div class="flex items-start justify-between">
                    <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Last activity') }}</flux:text>
                    <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:bg-violet-400/20 dark:text-violet-300">
                        <span class="text-sm font-semibold">↺</span>
                    </div>
                </div>
                <div class="mt-3 flex flex-col gap-1.5 text-sm text-zinc-800 dark:text-zinc-200">
                    @if ($kpis->lastMovementAtIso)
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Movement') }}: {{ \Carbon\Carbon::parse($kpis->lastMovementAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y') }}</span>
                    @endif
                    @if ($kpis->lastReceiptAtIso)
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Receipt') }}: {{ \Carbon\Carbon::parse($kpis->lastReceiptAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y') }}</span>
                    @endif
                    @if ($kpis->lastShipmentAtIso)
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Shipment') }}: {{ \Carbon\Carbon::parse($kpis->lastShipmentAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y') }}</span>
                    @endif
                    @if (! $kpis->lastMovementAtIso && ! $kpis->lastReceiptAtIso && ! $kpis->lastShipmentAtIso)
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">—</span>
                    @endif
                </div>
            </div>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200 bg-neutral-50 p-6 dark:border-zinc-700 dark:bg-zinc-800/50">
        <flux:heading size="lg">{{ __('Reorder alerts') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Set a minimum stock level. When on-hand is at or below this value, a warning appears on this page.') }}</flux:text>
        <form wire:submit="saveReorder" class="mt-4 grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="reorder_point_input" :label="__('Minimum / reorder level')" type="text" placeholder="e.g. 10" />
            <flux:input wire:model="reorder_qty_input" :label="__('Suggested reorder qty (optional)')" type="text" placeholder="e.g. 50" />
            <div class="sm:col-span-2">
                <flux:button type="submit" variant="primary">{{ __('Save reorder settings') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <div>
        <flux:heading size="lg">{{ __('Product Monitoring') }}</flux:heading>
        <flux:text class="mt-1 text-sm">{{ __('Inventory balance, purchase unit cost, and sales unit price for the selected period. Movement log below uses the same date range.') }}</flux:text>

        <div class="mt-4 flex flex-wrap gap-2">
            <flux:button type="button" wire:click="applyChartPresetLast7Days" size="sm" variant="outline">{{ __('Last 7 days') }}</flux:button>
            <flux:button type="button" wire:click="applyChartPresetLast30Days" size="sm" variant="outline">{{ __('Last 30 days') }}</flux:button>
            <flux:button type="button" wire:click="applyChartPresetLast90Days" size="sm" variant="outline">{{ __('Last 90 days') }}</flux:button>
            <flux:button type="button" wire:click="applyChartPresetYearToDate" size="sm" variant="outline">{{ __('Year to date') }}</flux:button>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <flux:input wire:model.live="chart_date_from" :label="__('From')" type="date" />
            <flux:input wire:model.live="chart_date_to" :label="__('To')" type="date" />
        </div>

        <div
            id="product360-charts-root"
            class="mt-6"
            wire:key="charts-{{ $chart_date_from }}-{{ $chart_date_to }}"
            data-product-360-charts
        >
            <script type="application/json" data-product-chart-json>{!! $this->chartSeriesJson !!}</script>

            <div class="grid gap-6 lg:grid-cols-1">
                <div class="rounded-xl border border-zinc-200 bg-zinc-100 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text class="text-sm font-medium">{{ __('Inventory Balance (Cumulative)') }}</flux:text>
                    <div class="relative mt-2 h-80 w-full">
                        <canvas data-chart="inventory"></canvas>
                    </div>
                </div>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-100 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-sm font-medium">{{ __('Purchase Unit Cost (PO lines)') }}</flux:text>
                        <div class="relative mt-2 h-80 w-full">
                            <canvas data-chart="purchase"></canvas>
                        </div>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-zinc-100 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-sm font-medium">{{ __('Sale Unit Price (SO lines)') }}</flux:text>
                        <div class="relative mt-2 h-80 w-full">
                            <canvas data-chart="sale"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <flux:card class="flex flex-col overflow-hidden border border-zinc-200 p-0 dark:border-zinc-700">
        <div class="border-b border-zinc-200 bg-zinc-100 px-6 py-5 dark:border-white/10 dark:bg-zinc-900/50">
            <flux:heading size="lg">{{ __('Inventory Movements') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Ledger for this product in the chart date range.') }}</flux:text>
        </div>

        @if ($this->movements->isEmpty())
            <div class="p-6">
                <flux:callout icon="arrows-right-left" color="zinc" inline :heading="__('No movements in range')" :text="__('Adjust the date range or record an adjustment.')" />
            </div>
        @else
            <flux:table>
                <flux:table.columns sticky class="bg-white dark:bg-white/10">
                    <flux:table.column class="px-6!">{{ __('When') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Type') }}</flux:table.column>
                    <flux:table.column align="end" class="px-6!">{{ __('Quantity') }}</flux:table.column>
                    <flux:table.column class="min-w-48 px-6! flex justify-end">{{ __('Source') }}</flux:table.column>
                    <flux:table.column class="px-6!">{{ __('Notes') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->movements as $movement)
                        @php
                            $source = $this->resolveMovementSource($movement);
                        @endphp
                        <flux:table.row :key="$movement->id">
                            <flux:table.cell class="whitespace-nowrap px-6! text-zinc-600 dark:text-zinc-400">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                    {{ $movement->created_at->timezone(config('app.timezone'))->translatedFormat('F j, Y') }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $movement->created_at->timezone(config('app.timezone'))->translatedFormat('h:i A') }}
                                </p>
                            </flux:table.cell>
                            <flux:table.cell class="px-6!">
                                <flux:badge :color="$movement->movement_type->fluxBadgeColor()" size="sm" inset="top bottom" class="capitalize">
                                    {{ $movement->movement_type->value }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end" class="px-6!">
                                <span @class([
                                    'tabular-nums font-medium',
                                    'text-emerald-600 dark:text-emerald-400' => (float) $movement->quantity > 0,
                                    'text-rose-600 dark:text-rose-400' => (float) $movement->quantity < 0,
                                    'text-zinc-700 dark:text-zinc-300' => (float) $movement->quantity == 0.0,
                                ])>{{ \Illuminate\Support\Number::format((float) $movement->quantity, maxPrecision: 4) }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="max-w-md px-6! text-right!">
                                @if ($source->url)
                                    <flux:link :href="$source->url" wire:navigate class="text-sm">{{ $source->label }}</flux:link>
                                @else
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $source->label }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="max-w-xs truncate px-6! text-zinc-600 dark:text-zinc-400">
                                {{ $movement->notes ?? '—' }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    @if (! $this->movements->isEmpty() && $this->movements->hasPages())
        <div class="flex justify-between items-center gap-4 px-1 sm:px-0">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Showing') }} {{ $this->movements->firstItem() }} {{ __('to') }} {{ $this->movements->lastItem() }} {{ __('of') }} {{ $this->movements->total() }}
            </flux:text>
            {{ $this->movements->links('vendor.pagination.numbers-only') }}
        </div>
    @endif

    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
                <flux:heading size="xl" class="font-bold text-2xl">{{ __('Lifecycle Timeline') }}</flux:heading>
                <flux:badge color="zinc" size="md" inset="top bottom" class="border">{{ \Illuminate\Support\Number::format(count($this->timeline)) }} {{ __('events') }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-sm">{{ __('Track procurement and sales transactions for this product, newest first.') }}</flux:text>
        </div>
    </div>
    @if (count($this->timeline) === 0)
        <div class="py-8 px-6">
            <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-6 text-center dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:callout icon="clock" color="zinc" inline :heading="__('No lifecycle activity yet')" :text="__('Create procurement or sales documents that reference this product to populate the transaction timeline.')" />
            </div>
        </div>
    @else
        <ul class="space-y-0 pb-6">
            @foreach ($this->timeline as $entry)
                @php
                    /** @var ProductActivityTimelineEntry $entry */
                @endphp
                <li class="relative pl-6 {{ $loop->last ? '' : 'pb-8' }}">
                    @if (! $loop->last)
                        <span class="absolute left-1.5 top-7 h-[calc(100%-1.25rem)] w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
                    @endif
                    <span class="absolute left-0 top-2.5 inline-flex h-3.5 w-3.5 rounded-full border-2 border-white bg-zinc-500 shadow-sm dark:border-zinc-900 dark:bg-zinc-400" aria-hidden="true"></span>
                    @php
                        $color = match ($this->categoryLabel($entry->category)) {
                            'RFQ' => 'blue',
                            'Purchase' => 'emerald',
                            'Receipt' => 'emerald',
                            'Movement' => 'violet',
                            'Sales order' => 'amber',
                            'Shipment' => 'amber',
                            'Invoice' => 'purple',
                            default => 'zinc',
                        };
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex items-start gap-2">
                            <flux:badge color="{{ $color }}" size="sm" inset="top bottom">
                                {{ $this->categoryLabel($entry->category) }}
                            </flux:badge>
                            <p class="font-semibold text-zinc-900 dark:text-white">
                                @if ($entry->url)
                                    <flux:link :href="$entry->url" wire:navigate>{{ $entry->title }}</flux:link>
                                @else
                                    {{ $entry->title }}
                                @endif
                            </p>
                        </div>
                        <div class="text-left text-sm tabular-nums text-zinc-400 dark:text-zinc-400 sm:text-right">
                            <p class="font-normal italic text-xs">{{ \Carbon\Carbon::parse($entry->occurredAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y - H:i') }}</p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>

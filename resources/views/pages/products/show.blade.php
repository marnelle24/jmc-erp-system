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
use App\Models\GoodsReceiptLine;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrderLine;
use App\Models\SalesShipmentLine;
use App\Support\TenantMoney;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Product'])]
#[Title('Product')]
class extends Component {
    use WithPagination;

    public Product $product;

    #[Url(as: 'tab', history: true)]
    public string $tab = 'overview';

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
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $this->normalizeTab($tab);

        if ($this->tab === 'movements') {
            $this->resetPage('movementsPage');
        }
    }

    private function normalizeTab(string $tab): string
    {
        $allowed = ['overview', 'sales_orders', 'supplier_sales_invoice', 'movements', 'timeline', 'settings'];

        return in_array($tab, $allowed, true) ? $tab : 'overview';
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

    /**
     * @return Collection<int, SalesOrderLine>
     */
    public function getSalesOrdersProperty(): Collection
    {
        $tenantId = (int) session('current_tenant_id');

        return SalesOrderLine::query()
            ->where('product_id', $this->product->id)
            ->whereHas('salesOrder', fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['salesOrder:id,tenant_id,order_date,customer_id', 'salesOrder.customer:id,name'])
            ->latest('id')
            ->limit(100)
            ->get();
    }

    /**
     * Purchase order lines for this product (supplier PO / purchase side), optionally filtered by PO order date.
     *
     * @return Collection<int, PurchaseOrderLine>
     */
    public function getSupplierSalesInvoicesProperty(): Collection
    {
        $tenantId = (int) session('current_tenant_id');

        return PurchaseOrderLine::query()
            ->where('product_id', $this->product->id)
            ->whereHas('purchaseOrder', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
                if ($this->chart_date_from !== '') {
                    $q->whereDate('order_date', '>=', $this->chart_date_from);
                }
                if ($this->chart_date_to !== '') {
                    $q->whereDate('order_date', '<=', $this->chart_date_to);
                }
            })
            ->with([
                'purchaseOrder:id,tenant_id,supplier_id,order_date,reference_code',
                'purchaseOrder.supplier:id,name',
            ])
            ->latest('id')
            ->limit(100)
            ->get();
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
            'rfq' => __('Purchase Request'),
            'purchase_order' => __('Purchase'),
            'goods_receipt' => __('Receipt'),
            'inventory_movement' => __('Movement'),
            'sales_invoice' => __('Sales Invoice'),
            'sales_order' => __('Sales Order'),
            'sales_shipment' => __('Sales Order'),
            default => $category,
        };
    }
}; ?>

@php
    /** @var ProductStockKpis $kpis */
    $kpis = $this->kpis;
    $tabClass = 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition';
    $tabActive = 'bg-white text-zinc-900 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-white/10';
    $tabIdle = 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100';
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:heading size="xl">{{ $product->name }}</flux:heading>
                @if ($product->sku)
                    <flux:badge color="zinc" size="sm" class="font-mono">{{ $product->sku }}</flux:badge>
                @endif
            </div>
            <flux:text class="mt-1">{{ $product->description ?: __('Product profile with sales, inventory, charts, and movement analytics.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('products.index')" variant="ghost" wire:navigate>
                <flux:icon name="arrow-left" class="w-4 h-4" />
                {{ __('Back') }}
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-800/80">
        <button type="button" wire:click="setTab('overview')" class="{{ $tabClass }} {{ $tab === 'overview' ? $tabActive : $tabIdle }}">
            {{ __('Overview') }}
        </button>
        <button type="button" wire:click="setTab('sales_orders')" class="{{ $tabClass }} {{ $tab === 'sales_orders' ? $tabActive : $tabIdle }}">
            {{ __('Customer Sales Orders') }}
        </button>
        <button type="button" wire:click="setTab('supplier_sales_invoice')" class="{{ $tabClass }} {{ $tab === 'supplier_sales_invoice' ? $tabActive : $tabIdle }}">
            {{ __('Supplier Sales Invoice (PO)') }}
        </button>
        <button type="button" wire:click="setTab('movements')" class="{{ $tabClass }} {{ $tab === 'movements' ? $tabActive : $tabIdle }}">
            {{ __('Movements') }}
        </button>
        <button type="button" wire:click="setTab('timeline')" class="{{ $tabClass }} {{ $tab === 'timeline' ? $tabActive : $tabIdle }}">
            {{ __('Timeline') }}
        </button>
        <button type="button" wire:click="setTab('settings')" class="{{ $tabClass }} {{ $tab === 'settings' ? $tabActive : $tabIdle }}">
            {{ __('Settings') }}
        </button>
    </div>

    @if ($tab === 'overview')
        @if ($this->isLowStock())
            <flux:callout icon="exclamation-triangle" color="amber" inline :heading="__('Below reorder level')" :text="__('On-hand quantity is at or below your minimum / reorder level. Consider creating a purchase order.')" />
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <flux:card class="border border-blue-300 bg-blue-200/50 p-4 dark:border-blue-300/40 dark:bg-blue-900/50">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('On hand') }}</flux:text>
                <flux:heading size="2xl" class="mt-1 tabular-nums text-3xl leading-tight sm:text-4xl">{{ \Illuminate\Support\Number::format((float) $kpis->onHand, maxPrecision: 4) }}</flux:heading>
            </flux:card>
            <flux:card class="border border-yellow-300 bg-yellow-200/50 p-4 dark:border-yellow-300/40 dark:bg-yellow-900/50">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Incoming (open PO)') }}</flux:text>
                <flux:heading size="2xl" class="mt-1 tabular-nums text-3xl leading-tight sm:text-4xl">{{ \Illuminate\Support\Number::format((float) $kpis->incoming, maxPrecision: 4) }}</flux:heading>
            </flux:card>
            <flux:card class="p-4 border border-red-300 bg-red-200/50 dark:border-red-300/40 dark:bg-red-900/50">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Committed (open SO)') }}</flux:text>
                <flux:heading size="2xl" class="mt-1 tabular-nums text-3xl leading-tight sm:text-4xl">{{ \Illuminate\Support\Number::format((float) $kpis->committed, maxPrecision: 4) }}</flux:heading>
            </flux:card>
            <flux:card class="p-4 flex flex-col gap-1 border border-emerald-300 bg-emerald-200/50 dark:border-emerald-300/40 dark:bg-emerald-900/50">
                <flux:text class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Last activity') }}</flux:text>
                <div class="flex gap-1">
                    <flux:text class="text-sm text-zinc-500">{{ __('Movement:') }}</flux:text>
                    <flux:text class="text-sm font-bold text-zinc-900">
                        {{ $kpis->lastMovementAtIso ? \Carbon\Carbon::parse($kpis->lastMovementAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y') : '—' }}
                    </flux:text>
                </div>
                <div class="flex gap-1">
                    <flux:text class="text-sm text-zinc-500">{{ __('Receipt:') }}</flux:text>
                    <flux:text class="text-sm font-bold text-zinc-900">
                        {{ $kpis->lastReceiptAtIso ? \Carbon\Carbon::parse($kpis->lastReceiptAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y') : '—' }}
                    </flux:text>
                </div>  
                <div class="flex gap-1">
                    <flux:text class="text-sm text-zinc-500">{{ __('Shipment:') }}</flux:text>
                    <flux:text class="text-sm font-bold text-zinc-900">
                        {{ $kpis->lastShipmentAtIso ? \Carbon\Carbon::parse($kpis->lastShipmentAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y') : '—' }}
                    </flux:text>
                </div>
            </flux:card>
        </div>


        <flux:card class="p-6">
            <div class="flex justify-between">
                <div class="flex flex-col gap-2">
                    <flux:heading size="xl">{{ __('Product Monitoring') }}</flux:heading>
                    <flux:text class="mt-2 text-sm">{{ __('Inventory balance, purchase unit cost, and sales unit price for the selected period.') }}</flux:text>
    
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="applyChartPresetLast7Days" size="sm" variant="outline">{{ __('Last 7 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetLast30Days" size="sm" variant="outline">{{ __('Last 30 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetLast90Days" size="sm" variant="outline">{{ __('Last 90 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetYearToDate" size="sm" variant="outline">{{ __('Year to date') }}</flux:button>
                    </div>
                </div>
    
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live="chart_date_from" :label="__('From')" type="date" />
                    <flux:input wire:model.live="chart_date_to" :label="__('To')" type="date" />
                </div>
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
                </div>
            </div>
        </flux:card>
    @endif

    @if ($tab === 'sales_orders')
        <flux:card class="p-6">
            <div class="flex justify-between">
                <div class="flex flex-col gap-2">
                    <flux:heading size="xl">{{ __('Sales Orders Price Analysis') }}</flux:heading>
                    <flux:text class="mt-2 text-sm">{{ __('Sales order prices for the selected period.') }}</flux:text>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="applyChartPresetLast7Days" size="sm" variant="outline">{{ __('Last 7 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetLast30Days" size="sm" variant="outline">{{ __('Last 30 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetLast90Days" size="sm" variant="outline">{{ __('Last 90 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetYearToDate" size="sm" variant="outline">{{ __('Year to date') }}</flux:button>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live="chart_date_from" :label="__('From')" type="date" />
                    <flux:input wire:model.live="chart_date_to" :label="__('To')" type="date" />
                </div>
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
                        <flux:text class="text-sm font-medium">{{ __('Sale Unit Price (SO lines)') }}</flux:text>
                        <div class="relative mt-2 h-80 w-full">
                            <canvas data-chart="sale"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>
        <flux:card class="overflow-hidden p-0">
            @if (count($this->salesOrders) === 0)
                <div class="p-8">
                    <flux:callout icon="shopping-cart" color="zinc" inline :heading="__('No sales orders')" :text="__('Sales orders linked to this product will appear here.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('Sales Order No.') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Customer') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Sell price') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Date') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->salesOrders as $salesOrder)
                            <flux:table.row :key="'so-line-'.$salesOrder->id">
                                <flux:table.cell variant="strong" class="px-6!">Sales Order #{{ $salesOrder->sales_order_id }}</flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    {{ $salesOrder->salesOrder?->customer?->name ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">
                                    {{ \Illuminate\Support\Number::format((float) $salesOrder->quantity_ordered, maxPrecision: 4) }}
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">
                                    {{ TenantMoney::format((float) $salesOrder->unit_price, null, 2) }}
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    {{ $salesOrder->salesOrder?->order_date?->translatedFormat('F j, Y') ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button size="xs" variant="primary" :href="route('sales.orders.show', $salesOrder->sales_order_id)" wire:navigate>{{ __('View') }}</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($tab === 'supplier_sales_invoice')
        <flux:card class="p-6">
            <div class="flex justify-between">
                <div class="flex flex-col gap-2">
                    <flux:heading size="xl">{{ __('Supplier Sales Invoice (PO)') }}</flux:heading>
                    <flux:text class="mt-2 text-sm">{{ __('Supplier sales invoice for the selected period.') }}</flux:text>
                    <div class="flex gap-2">
                        <flux:button type="button" wire:click="applyChartPresetLast7Days" size="sm" variant="outline">{{ __('Last 7 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetLast30Days" size="sm" variant="outline">{{ __('Last 30 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetLast90Days" size="sm" variant="outline">{{ __('Last 90 days') }}</flux:button>
                        <flux:button type="button" wire:click="applyChartPresetYearToDate" size="sm" variant="outline">{{ __('Year to date') }}</flux:button>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model.live="chart_date_from" :label="__('From')" type="date" />
                    <flux:input wire:model.live="chart_date_to" :label="__('To')" type="date" />
                </div>
            </div>
            <div
                id="product360-charts-root"
                class="mt-6"
                wire:key="charts-{{ $chart_date_from }}-{{ $chart_date_to }}"
                data-product-360-charts
            >
                <script type="application/json" data-product-chart-json>{!! $this->chartSeriesJson !!}</script>
                <div class="grid gap-6 md:grid-cols-1">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-100 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:text class="text-sm font-medium">{{ __('Purchase Unit Cost (PO lines)') }}</flux:text>
                        <div class="relative mt-2 h-80 w-full">
                            <canvas data-chart="purchase"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>
        <flux:card class="overflow-hidden p-0">
            @if (count($this->supplierSalesInvoices) === 0)
                <div class="p-8">
                    <flux:callout icon="shopping-cart" color="zinc" inline :heading="__('No supplier sales invoices')" :text="__('Supplier sales invoices linked to this product will appear here.')" />
                </div>
            @else
                <flux:table>
                    <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                        <flux:table.column class="px-6!">{{ __('PO') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Supplier') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Quantity') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Unit cost') }}</flux:table.column>
                        <flux:table.column class="px-6!">{{ __('Order date') }}</flux:table.column>
                        <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->supplierSalesInvoices as $poLine)
                            <flux:table.row :key="'po-line-'.$poLine->id">
                                <flux:table.cell variant="strong" class="px-6!">
                                    {{ $poLine->purchaseOrder?->reference_code ?? __('PO #:id', ['id' => $poLine->purchase_order_id]) }}
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    {{ $poLine->purchaseOrder?->supplier?->name ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6! tabular-nums">
                                    {{ \Illuminate\Support\Number::format((float) $poLine->quantity_ordered, maxPrecision: 4) }}
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    {{ TenantMoney::format((float) $poLine->unit_cost, null, 2) }}
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    {{ $poLine->purchaseOrder?->order_date?->translatedFormat('F j, Y') ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <flux:button size="xs" variant="primary" :href="route('procurement.purchase-orders.show', $poLine->purchase_order_id)" wire:navigate>{{ __('View') }}</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>
    @endif

    @if ($tab === 'movements')
        <flux:card class="flex flex-col overflow-hidden border border-zinc-200 p-0 dark:border-zinc-700">
            <div class="border-b border-zinc-200 bg-zinc-100 px-6 py-5 dark:border-white/10 dark:bg-zinc-900/50">
                <flux:heading size="lg">{{ __('Inventory Movements') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Ledger for this product in the selected date range.') }}</flux:text>
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
                        <flux:table.column align="end" class="px-6!">{{ __('Purchase Price') }}</flux:table.column>
                        <flux:table.column align="end" class="px-6!">{{ __('Sales Price') }}</flux:table.column>
                        <flux:table.column class="min-w-48 px-6! flex justify-end">{{ __('Source') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->movements as $movement)
                            @php
                                $source = $this->resolveMovementSource($movement);
                                $purchasePrice = null;
                                $salesPrice = null;

                                if ($movement->movement_type->value === 'receipt' && $movement->reference instanceof GoodsReceiptLine) {
                                    $purchasePrice = $movement->reference->unit_cost;
                                } elseif ($movement->movement_type->value === 'issue' && $movement->reference instanceof SalesShipmentLine) {
                                    $salesPrice = $movement->reference->salesOrderLine?->unit_price;
                                }
                            @endphp
                            <flux:table.row :key="$movement->id">
                                <flux:table.cell class="whitespace-nowrap px-6! text-zinc-600 dark:text-zinc-400">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $movement->created_at->timezone(config('app.timezone'))->translatedFormat('F j, Y') }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $movement->created_at->timezone(config('app.timezone'))->translatedFormat('h:i A') }}</p>
                                </flux:table.cell>
                                <flux:table.cell class="px-6!">
                                    <flux:badge :color="$movement->movement_type->fluxBadgeColor()" size="sm" inset="top bottom" class="capitalize">
                                        {{ $movement->movement_type->value === 'receipt' ? __('IN') : __('OUT') }}
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
                                <flux:table.cell align="end" class="px-6!">
                                    <span class="tabular-nums font-medium">@if ($purchasePrice !== null){{ TenantMoney::format((float) $purchasePrice, null, 2) }}@endif</span>
                                </flux:table.cell>
                                <flux:table.cell align="end" class="px-6!">
                                    <span class="tabular-nums font-medium">@if ($salesPrice !== null){{ TenantMoney::format((float) $salesPrice, null, 2) }}@endif</span>
                                </flux:table.cell>
                                <flux:table.cell class="max-w-md px-6! text-right!">
                                    @if ($source->url)
                                        <flux:link :href="$source->url" wire:navigate class="text-sm">{{ $source->label }}</flux:link>
                                    @else
                                        <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $source->label }}</span>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        @if (! $this->movements->isEmpty() && $this->movements->hasPages())
            <div class="flex items-center justify-between gap-4 px-1 sm:px-0">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Showing') }} {{ $this->movements->firstItem() }} {{ __('to') }} {{ $this->movements->lastItem() }} {{ __('of') }} {{ $this->movements->total() }}</flux:text>
                {{ $this->movements->links('vendor.pagination.numbers-only') }}
            </div>
        @endif
    @endif

    @if ($tab === 'timeline')
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" class="text-2xl font-bold">{{ __('Lifecycle Timeline') }}</flux:heading>
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
                        $color = match ($this->categoryLabel($entry->category)) {
                            'Purchase Request' => 'blue',
                            'Purchase' => 'amber',
                            'Receipt' => 'purple',
                            'Movement' => 'violet',
                            'Sales Invoice' => 'emerald',
                            'Sales Order' => 'emerald',
                            'Invoice' => 'purple',
                            default => 'zinc',
                        };
                    @endphp
                    <li class="relative pl-6 {{ $loop->last ? '' : 'pb-8' }}">
                        @if (! $loop->last)
                            <span class="absolute left-1.5 top-7 h-[calc(100%-1.25rem)] w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
                        @endif
                        <span class="absolute left-0 top-2.5 inline-flex h-3.5 w-3.5 rounded-full border-2 border-white bg-zinc-500 shadow-sm dark:border-zinc-900 dark:bg-zinc-400" aria-hidden="true"></span>
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex items-start gap-2">
                                <flux:badge color="{{ $color }}" size="sm" inset="top bottom">{{ $this->categoryLabel($entry->category) }}</flux:badge>
                                <p class="font-semibold text-zinc-900 dark:text-white">
                                    @if ($entry->url)
                                        <flux:link :href="$entry->url" wire:navigate>{{ $entry->title }}</flux:link>
                                    @else
                                        {{ $entry->title }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-left text-sm tabular-nums text-zinc-400 dark:text-zinc-400 sm:text-right">
                                <p class="text-xs font-normal italic">{{ \Carbon\Carbon::parse($entry->occurredAtIso)->timezone(config('app.timezone'))->translatedFormat('F j, Y - H:i') }}</p>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif

    @if ($tab === 'settings')
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
    @endif
</div>

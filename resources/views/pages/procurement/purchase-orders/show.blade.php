<?php

use App\Domains\Accounting\Services\RecordSupplierPaymentService;
use App\Domains\Accounting\Support\OpenItemStatusResolver;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierPaymentMethod;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\AccountsPayable;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Purchase order'])]
#[Title('Purchase order')]
class extends Component {
    public PurchaseOrder $purchaseOrder;

    public string $amount = '';

    public string $payment_method = '';

    public string $paid_at = '';

    public string $reference = '';

    public string $notes = '';

    /** @var array<int|string, string> */
    public array $allocationAmounts = [];

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->purchaseOrder = PurchaseOrder::query()
            ->where('tenant_id', $tenantId)
            ->with(['supplier', 'lines.product', 'goodsReceipts'])
            ->findOrFail($id);

        Gate::authorize('view', $this->purchaseOrder);

        $this->payment_method = SupplierPaymentMethod::Cash->value;
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');
    }

    public function preparePaymentModal(): void
    {
        Gate::authorize('create', SupplierPayment::class);
        $this->allocationAmounts = [];
        $this->amount = '';
        $this->reference = '';
        $this->notes = '';
        $this->payment_method = SupplierPaymentMethod::Cash->value;
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');
    }

    public function getOpenPayablesForPurchaseOrderProperty()
    {
        $tenantId = (int) session('current_tenant_id');
        $grIds = $this->purchaseOrder->goodsReceipts->pluck('id');
        if ($grIds->isEmpty()) {
            return collect();
        }

        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('goods_receipt_id', $grIds)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->orderBy('posted_at')
            ->get();
    }

    public function recordPurchaseOrderPayment(RecordSupplierPaymentService $service): void
    {
        Gate::authorize('create', SupplierPayment::class);

        $allowedIds = $this->openPayablesForPurchaseOrder->pluck('id')->map(fn ($id) => (int) $id)->all();

        $allocations = [];
        foreach ($this->allocationAmounts as $apId => $amt) {
            $amt = trim((string) $amt);
            if ($amt === '' || bccomp($amt, '0', 4) !== 1) {
                continue;
            }
            $id = (int) $apId;
            if (! in_array($id, $allowedIds, true)) {
                Flux::toast(variant: 'danger', text: __('Invalid payable allocation for this purchase order.'));

                return;
            }
            $allocations[] = [
                'accounts_payable_id' => $id,
                'amount' => $amt,
            ];
        }

        $paidAt = $this->paid_at !== ''
            ? Carbon::parse($this->paid_at)->toDateTimeString()
            : now()->toDateTimeString();

        $payload = [
            'supplier_id' => (string) $this->purchaseOrder->supplier_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'paid_at' => $paidAt,
            'reference' => $this->reference !== '' ? $this->reference : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'allocations' => $allocations,
        ];

        Validator::make($payload, (new StoreSupplierPaymentRequest)->rules())->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                (int) $this->purchaseOrder->supplier_id,
                (string) $payload['amount'],
                $paidAt,
                $payload['reference'],
                $payload['notes'],
                (string) $payload['payment_method'],
                $allocations,
            );
            $this->modal('add-po-payment')->close();
            Flux::toast(variant: 'success', text: __('Supplier payment recorded.'));
            $this->preparePaymentModal();
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Purchase order #:id', ['id' => $purchaseOrder->id]) }}</flux:heading>
            <flux:text class="mt-1">
                {{ $purchaseOrder->supplier->name }} · {{ $purchaseOrder->order_date->format('Y-m-d') }}
                · {{ ucfirst(str_replace('_', ' ', $purchaseOrder->status->value)) }}
            </flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if (Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isNotEmpty())
                <flux:modal.trigger name="add-po-payment">
                    <flux:button wire:click="preparePaymentModal" variant="primary">{{ __('Add payment') }}</flux:button>
                </flux:modal.trigger>
            @endif
            @if (! in_array($purchaseOrder->status, [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received], true))
                <flux:button
                    :variant="Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isNotEmpty() ? 'ghost' : 'primary'"
                    :href="route('procurement.purchase-orders.receive', $purchaseOrder)"
                    wire:navigate
                >{{ __('Receive goods') }}</flux:button>
            @endif
            <flux:button :href="route('procurement.purchase-orders.index')" variant="ghost" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
    </div>

    @if (Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isEmpty() && $purchaseOrder->goodsReceipts->isNotEmpty())
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('There are no open payables for this order. If liabilities were not posted yet, record them from accounting after receiving goods.') }}
        </flux:text>
    @elseif (Gate::allows('create', SupplierPayment::class) && $purchaseOrder->goodsReceipts->isEmpty())
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Receive goods and post accounts payable before you can allocate a payment to this order.') }}
        </flux:text>
    @endif

    @if ($purchaseOrder->notes)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:text>{{ $purchaseOrder->notes }}</flux:text>
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
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Ordered') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Received') }}</th>
                        <th class="px-6 py-3 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($purchaseOrder->lines as $line)
                        @php
                            $received = $line->totalReceivedQuantity();
                            $remaining = bcsub((string) $line->quantity_ordered, $received, 4);
                        @endphp
                        <tr wire:key="pol-{{ $line->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $line->product->name }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $line->quantity_ordered, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $received, maxPrecision: 4) }}</td>
                            <td class="px-6 py-3 text-end tabular-nums">{{ \Illuminate\Support\Number::format((float) $remaining, maxPrecision: 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if (Gate::allows('create', SupplierPayment::class) && $this->openPayablesForPurchaseOrder->isNotEmpty())
        <flux:modal name="add-po-payment" class="max-w-lg">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ __('Add payment') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Allocate to open payables for :supplier from this order. Total allocations must match the payment amount.', ['supplier' => $purchaseOrder->supplier->name]) }}
                    </flux:text>
                </div>

                <form wire:submit="recordPurchaseOrderPayment" class="flex flex-col gap-4">
                    <flux:input
                        wire:model="amount"
                        :label="__('Payment amount')"
                        type="text"
                        inputmode="decimal"
                        required
                    />

                    <flux:select wire:model="payment_method" :label="__('Payment method')" required>
                        @foreach (SupplierPaymentMethod::cases() as $method)
                            <flux:select.option :value="$method->value">{{ $method->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="paid_at" :label="__('Paid at')" type="datetime-local" required />

                    <flux:input wire:model="reference" :label="__('Reference (optional)')" type="text" />

                    <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

                    <div>
                        <flux:heading size="sm" class="mb-2">{{ __('Allocate to payables for this order') }}</flux:heading>
                        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                    <tr>
                                        <th class="px-4 py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Payable') }}</th>
                                        <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                                        <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach ($this->openPayablesForPurchaseOrder as $ap)
                                        @php
                                            $rem = OpenItemStatusResolver::remaining(
                                                (string) $ap->total_amount,
                                                (string) $ap->amount_paid,
                                            );
                                        @endphp
                                        <tr wire:key="po-pay-ap-{{ $ap->id }}">
                                            <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">#{{ $ap->id }}</td>
                                            <td class="px-4 py-2 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $rem, maxPrecision: 4) }}</td>
                                            <td class="px-4 py-2 text-end">
                                                <flux:input
                                                    class="max-w-40 ms-auto"
                                                    wire:model="allocationAmounts.{{ $ap->id }}"
                                                    type="text"
                                                    inputmode="decimal"
                                                    :placeholder="'0'"
                                                />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" type="submit">{{ __('Save payment') }}</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>
    @endif
</div>

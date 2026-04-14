<?php

use App\Domains\Accounting\Services\RecordSupplierPaymentService;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\SupplierPaymentMethod;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Models\AccountsPayable;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Supplier payment'])]
#[Title('Supplier payment')]
class extends Component {
    public string $supplier_id = '';

    public string $amount = '';

    public string $payment_method = '';

    public string $paid_at = '';

    public string $reference = '';

    public string $notes = '';

    /** @var array<int|string, string> */
    public array $allocationAmounts = [];

    public function mount(): void
    {
        Gate::authorize('create', SupplierPayment::class);
        $this->payment_method = SupplierPaymentMethod::Cash->value;
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');
    }

    public function updatedSupplierId(): void
    {
        $this->allocationAmounts = [];
    }

    public function getSuppliersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Supplier::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function getOpenPayablesProperty()
    {
        if ($this->supplier_id === '' || $this->supplier_id === '0') {
            return collect();
        }

        $tenantId = (int) session('current_tenant_id');

        return AccountsPayable::query()
            ->where('tenant_id', $tenantId)
            ->where('supplier_id', (int) $this->supplier_id)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->orderBy('posted_at')
            ->get();
    }

    public function recordPayment(RecordSupplierPaymentService $service): void
    {
        Gate::authorize('create', SupplierPayment::class);

        $allocations = [];
        foreach ($this->allocationAmounts as $apId => $amt) {
            $amt = trim((string) $amt);
            if ($amt === '' || bccomp($amt, '0', 4) !== 1) {
                continue;
            }
            $allocations[] = [
                'accounts_payable_id' => (int) $apId,
                'amount' => $amt,
            ];
        }

        $paidAt = $this->paid_at !== ''
            ? Carbon::parse($this->paid_at)->toDateTimeString()
            : now()->toDateTimeString();

        $payload = [
            'supplier_id' => $this->supplier_id,
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
                (int) $payload['supplier_id'],
                (string) $payload['amount'],
                $paidAt,
                $payload['reference'],
                $payload['notes'],
                (string) $payload['payment_method'],
                $allocations,
            );
            Flux::toast(variant: 'success', text: __('Supplier payment recorded.'));
            $this->redirect(route('accounting.payables.index', absolute: false), navigate: true);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Record supplier payment') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Allocate the payment to one or more open payables for the supplier. Total allocations must match the payment amount.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="recordPayment" class="flex flex-col gap-6">
            <flux:select
                wire:model.live="supplier_id"
                :label="__('Supplier')"
                :placeholder="__('Choose a supplier…')"
                required
            >
                @foreach ($this->suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>

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

            @if ($this->openPayables->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-2">{{ __('Allocate to open payables') }}</flux:heading>
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
                                @foreach ($this->openPayables as $ap)
                                    @php
                                        $rem = \App\Domains\Accounting\Support\OpenItemStatusResolver::remaining(
                                            (string) $ap->total_amount,
                                            (string) $ap->amount_paid,
                                        );
                                    @endphp
                                    <tr wire:key="alloc-ap-{{ $ap->id }}">
                                        <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">#{{ $ap->id }}</td>
                                        <td class="px-4 py-2 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Number::format((float) $rem, maxPrecision: 4) }}</td>
                                        <td class="px-4 py-2 text-end">
                                            <flux:input
                                                class="max-w-[10rem] ms-auto"
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
            @elseif ($this->supplier_id !== '' && $this->supplier_id !== '0')
                <flux:text class="text-zinc-500">{{ __('No open payables for this supplier.') }}</flux:text>
            @endif

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save payment') }}</flux:button>
                <flux:button :href="route('accounting.payables.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>

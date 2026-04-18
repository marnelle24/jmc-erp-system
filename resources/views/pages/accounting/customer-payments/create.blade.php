<?php

use App\Domains\Accounting\Services\RecordCustomerPaymentService;
use App\Enums\AccountingOpenItemStatus;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Models\AccountsReceivable;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Support\TenantMoney;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Customer payment'])]
#[Title('Customer payment')]
class extends Component {
    public string $customer_id = '';

    public string $amount = '';

    public string $paid_at = '';

    public string $reference = '';

    public string $notes = '';

    /** @var array<int|string, string> */
    public array $allocationAmounts = [];

    public function mount(): void
    {
        Gate::authorize('create', CustomerPayment::class);
        $this->paid_at = Carbon::now()->format('Y-m-d\TH:i');
    }

    public function updatedCustomerId(): void
    {
        $this->allocationAmounts = [];
    }

    public function getCustomersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function getOpenReceivablesProperty()
    {
        if ($this->customer_id === '' || $this->customer_id === '0') {
            return collect();
        }

        $tenantId = (int) session('current_tenant_id');

        return AccountsReceivable::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', (int) $this->customer_id)
            ->whereIn('status', [AccountingOpenItemStatus::Open, AccountingOpenItemStatus::Partial])
            ->orderBy('posted_at')
            ->get();
    }

    public function recordPayment(RecordCustomerPaymentService $service): void
    {
        Gate::authorize('create', CustomerPayment::class);

        $allocations = [];
        foreach ($this->allocationAmounts as $arId => $amt) {
            $amt = trim((string) $amt);
            if ($amt === '' || bccomp($amt, '0', 4) !== 1) {
                continue;
            }
            $allocations[] = [
                'accounts_receivable_id' => (int) $arId,
                'amount' => $amt,
            ];
        }

        $paidAt = $this->paid_at !== ''
            ? Carbon::parse($this->paid_at)->toDateTimeString()
            : now()->toDateTimeString();

        $payload = [
            'customer_id' => $this->customer_id,
            'amount' => $this->amount,
            'paid_at' => $paidAt,
            'reference' => $this->reference !== '' ? $this->reference : null,
            'notes' => $this->notes !== '' ? $this->notes : null,
            'allocations' => $allocations,
        ];

        Validator::make($payload, (new StoreCustomerPaymentRequest)->rules())->validate();

        try {
            $service->execute(
                (int) session('current_tenant_id'),
                (int) $payload['customer_id'],
                (string) $payload['amount'],
                $paidAt,
                $payload['reference'],
                $payload['notes'],
                $allocations,
            );
            Flux::toast(variant: 'success', text: __('Customer payment recorded.'));
            $this->redirect(route('accounting.receivables.index', absolute: false), navigate: true);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Record customer payment') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Allocate the receipt to one or more open receivables for the customer. Total allocations must match the payment amount.') }}</flux:text>
    </div>

    <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <form wire:submit="recordPayment" class="flex flex-col gap-6">
            <flux:select
                wire:model.live="customer_id"
                :label="__('Customer')"
                :placeholder="__('Choose a customer…')"
                required
            >
                @foreach ($this->customers as $customer)
                    <flux:select.option :value="$customer->id">{{ $customer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="amount"
                :label="__('Payment amount')"
                type="text"
                inputmode="decimal"
                required
            />

            <flux:input wire:model="paid_at" :label="__('Received at')" type="datetime-local" required />

            <flux:input wire:model="reference" :label="__('Reference (optional)')" type="text" />

            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            @if ($this->openReceivables->isNotEmpty())
                <div>
                    <flux:heading size="sm" class="mb-2">{{ __('Allocate to open receivables') }}</flux:heading>
                    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium text-zinc-600 dark:text-zinc-400">{{ __('Receivable') }}</th>
                                    <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Remaining') }}</th>
                                    <th class="px-4 py-2 text-end font-medium text-zinc-600 dark:text-zinc-400">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($this->openReceivables as $ar)
                                    @php
                                        $rem = \App\Domains\Accounting\Support\OpenItemStatusResolver::remaining(
                                            (string) $ar->total_amount,
                                            (string) $ar->amount_paid,
                                        );
                                    @endphp
                                    <tr wire:key="alloc-ar-{{ $ar->id }}">
                                        <td class="px-4 py-2 font-medium text-zinc-900 dark:text-zinc-100">#{{ $ar->id }} ({{ __('inv.') }} #{{ $ar->sales_invoice_id }})</td>
                                        <td class="px-4 py-2 text-end tabular-nums text-zinc-600 dark:text-zinc-400">{{ TenantMoney::format((float) $rem, null, 4) }}</td>
                                        <td class="px-4 py-2 text-end">
                                            <flux:input
                                                class="max-w-[10rem] ms-auto"
                                                wire:model="allocationAmounts.{{ $ar->id }}"
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
            @elseif ($this->customer_id !== '' && $this->customer_id !== '0')
                <flux:text class="text-zinc-500">{{ __('No open receivables for this customer.') }}</flux:text>
            @endif

            <div class="flex flex-wrap gap-3">
                <flux:button variant="primary" type="submit">{{ __('Save payment') }}</flux:button>
                <flux:button :href="route('accounting.receivables.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>
</div>

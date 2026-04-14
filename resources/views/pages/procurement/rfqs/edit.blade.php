<?php

use App\Domains\Procurement\Services\UpdateRfqService;
use App\Enums\RfqLineUnitType;
use App\Enums\RfqStatus;
use App\Http\Requests\UpdateRfqRequest;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app', ['title' => 'Edit RFQ'])]
#[Title('Edit RFQ')]
class extends Component {
    public Rfq $rfq;

    public string $supplier_id = '';

    public string $title = '';

    public string $notes = '';

    /** @var list<array{product_id: string, quantity: string, unit_type: string, unit_price: string, notes: string}> */
    public array $lines = [];

    public function mount(int $id): void
    {
        $tenantId = (int) session('current_tenant_id');
        $this->rfq = Rfq::query()
            ->where('tenant_id', $tenantId)
            ->with('lines')
            ->findOrFail($id);

        Gate::authorize('update', $this->rfq);

        if ($this->rfq->status === RfqStatus::Closed || $this->rfq->purchaseOrders()->exists()) {
            $this->redirect(route('procurement.rfqs.show', $this->rfq, absolute: false), navigate: true);

            return;
        }

        if (! in_array($this->rfq->status, [RfqStatus::PendingForApproval, RfqStatus::ApprovedNoPo, RfqStatus::Sent], true)) {
            $this->redirect(route('procurement.rfqs.show', $this->rfq, absolute: false), navigate: true);

            return;
        }

        $this->supplier_id = (string) $this->rfq->supplier_id;
        $this->title = $this->rfq->title ?? '';
        $this->notes = $this->rfq->notes ?? '';

        $this->lines = [];
        foreach ($this->rfq->lines as $line) {
            $this->lines[] = [
                'product_id' => (string) $line->product_id,
                'quantity' => (string) $line->quantity,
                'unit_type' => $line->unit_type->value,
                'unit_price' => $line->unit_price !== null ? (string) $line->unit_price : '',
                'notes' => $line->notes ?? '',
            ];
        }
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => '']];
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->lines === []) {
            $this->lines = [['product_id' => '', 'quantity' => '', 'unit_type' => RfqLineUnitType::Piece->value, 'unit_price' => '', 'notes' => '']];
        }
    }

    public function save(UpdateRfqService $service): void
    {
        Gate::authorize('update', $this->rfq);

        $validated = $this->validate((new UpdateRfqRequest)->rules());

        try {
            $service->execute($this->rfq, $validated);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('RFQ updated.'));

        $this->redirect(route('procurement.rfqs.show', $this->rfq, absolute: false), navigate: true);
    }

    public function getSuppliersProperty()
    {
        return Supplier::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->orderBy('name')
            ->get();
    }

    public function getProductsProperty()
    {
        return Product::query()
            ->where('tenant_id', (int) session('current_tenant_id'))
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Edit :code', ['code' => $rfq->reference_code]) }}</flux:heading>
        <flux:text class="mt-1">{{ __('Update supplier, notes, and line items.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:select wire:model="supplier_id" :label="__('Supplier')" :placeholder="__('Choose…')" required>
            @foreach ($this->suppliers as $supplier)
                <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model="title" :label="__('Title')" type="text" :placeholder="__('Optional short label')" />

        <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Line items') }}</flux:heading>
                <flux:button type="button" wire:click="addLine" variant="ghost" size="sm">{{ __('Add line') }}</flux:button>
            </div>

            @foreach ($lines as $index => $line)
                <div wire:key="rfq-edit-line-{{ $index }}" class="grid gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-3">
                        <flux:select wire:model="lines.{{ $index }}.product_id" :label="__('Product')" :placeholder="__('Choose…')" required>
                            @foreach ($this->products as $product)
                                <flux:select.option :value="$product->id">{{ $product->name }} @if ($product->sku) ({{ $product->sku }}) @endif</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.quantity" :label="__('Qty')" type="text" inputmode="decimal" required />
                    </div>
                    <div class="md:col-span-2">
                        <flux:select wire:model="lines.{{ $index }}.unit_type" :label="__('Unit type')" required>
                            @foreach (RfqLineUnitType::cases() as $unitType)
                                <flux:select.option :value="$unitType->value">{{ $unitType->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.unit_price" :label="__('Est. unit price')" type="text" inputmode="decimal" :description="__('Optional. Can be updated from the purchase order when goods are received.')" />
                    </div>
                    <div class="md:col-span-2">
                        <flux:input wire:model="lines.{{ $index }}.notes" :label="__('Line notes')" type="text" />
                    </div>
                    <div class="md:col-span-1 flex justify-end pb-2">
                        <flux:button type="button" wire:click="removeLine({{ $index }})" variant="ghost" size="sm">{{ __('Remove') }}</flux:button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-3">
            <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            <flux:button :href="route('procurement.rfqs.show', $rfq)" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>

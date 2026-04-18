<?php

use App\Domains\Crm\Services\CreateSupplierService;
use App\Domains\Crm\Services\UpdateSupplierService;
use App\Enums\SupplierStatus;
use App\Http\Requests\SupplierPayloadRules;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Suppliers'])]
#[Title('Suppliers')]
class extends Component {
    use WithPagination;

    public string $name = '';

    public string $code = '';

    public string $status = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $payment_terms = '';

    public string $tax_id = '';

    public string $notes = '';

    public ?int $editingSupplierId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Supplier::class);
        $this->status = SupplierStatus::Active->value;
    }

    public function startEdit(int $supplierId): void
    {
        $tenantId = (int) session('current_tenant_id');

        $supplier = Supplier::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($supplierId)
            ->firstOrFail();

        Gate::authorize('update', $supplier);

        $this->editingSupplierId = $supplier->id;
        $this->name = $supplier->name;
        $this->code = $supplier->code ?? '';
        $this->status = $supplier->status->value;
        $this->email = $supplier->email ?? '';
        $this->phone = $supplier->phone ?? '';
        $this->address = $supplier->address ?? '';
        $this->payment_terms = $supplier->payment_terms ?? '';
        $this->tax_id = $supplier->tax_id ?? '';
        $this->notes = $supplier->notes ?? '';
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->editingSupplierId = null;
        $this->reset('name', 'code', 'email', 'phone', 'address', 'payment_terms', 'tax_id', 'notes');
        $this->status = SupplierStatus::Active->value;
        $this->resetValidation();
    }

    public function saveSupplier(CreateSupplierService $create, UpdateSupplierService $update): void
    {
        $tenantId = (int) session('current_tenant_id');

        if ($this->editingSupplierId !== null) {
            $supplier = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($this->editingSupplierId)
                ->firstOrFail();

            Gate::authorize('update', $supplier);

            $validated = $this->validate(SupplierPayloadRules::rules($this->editingSupplierId));
            $update->execute($supplier, $validated);

            $this->cancelEdit();

            Flux::toast(variant: 'success', text: __('Supplier updated.'));

            return;
        }

        Gate::authorize('create', Supplier::class);

        $validated = $this->validate(SupplierPayloadRules::rules());

        $create->execute($tenantId, $validated);

        $this->reset('name', 'code', 'email', 'phone', 'address', 'payment_terms', 'tax_id', 'notes');
        $this->status = SupplierStatus::Active->value;
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Supplier added.'));
    }

    public function getSuppliersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Supplier::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Suppliers Management') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Manage your suppliers. Add, edit, and maintain vendor contact records.') }}</flux:text>
    </div>

    <div class="flex w-full flex-col items-stretch gap-6 md:flex-row md:items-start">
        <div class="min-w-0 w-full basis-full md:basis-0 md:flex-3">
            <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                    <flux:heading size="lg">{{ __('Suppliers Master List') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('All suppliers recorded in the system. Contact details appear on procurement documents.') }}</flux:text>
                </div>

                @if ($this->suppliers->isEmpty())
                    <div class="p-6">
                        <flux:callout icon="users" color="zinc" inline :heading="__('No suppliers yet')" :text="__('Add a supplier using the form beside this list. Email and phone are optional.')" />
                    </div>
                @else
                    <flux:table
                        :paginate="$this->suppliers->hasPages() ? $this->suppliers : null"
                        pagination:scroll-to
                    >
                        <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                            <flux:table.column class="px-6!">{{ __('Name') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Code') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Status') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Email') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Phone') }}</flux:table.column>
                            <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->suppliers as $supplier)
                                <flux:table.row :key="$supplier->id">
                                    <flux:table.cell variant="strong" class="px-6!">
                                        <a href="{{ route('suppliers.show', $supplier->id) }}" wire:navigate class="text-zinc-800 font-bold transition-all duration-300 hover:text-zinc-600 hover:underline dark:text-zinc-200">
                                            {{ $supplier->name }}
                                        </a>
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6! font-mono text-sm">
                                        @if ($supplier->code)
                                            {{ $supplier->code }}
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6!">{{ $supplier->status->label() }}</flux:table.cell>
                                    <flux:table.cell class="px-6!">
                                        @if ($supplier->email)
                                            <span class="text-zinc-700 dark:text-zinc-200">{{ $supplier->email }}</span>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6!">
                                        @if ($supplier->phone)
                                            <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $supplier->phone }}</span>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="flex justify-end gap-1 px-6!">
                                        <flux:button
                                            type="button"
                                            size="xs"
                                            variant="primary"
                                            :href="route('suppliers.show', $supplier->id)"
                                            wire:navigate
                                            inset="top bottom"
                                            class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                        >
                                            {{ __('View') }}
                                        </flux:button>
                                        <flux:button
                                            type="button"
                                            size="xs"
                                            variant="primary"
                                            wire:click="startEdit({{ $supplier->id }})"
                                            inset="top bottom"
                                            class="border border-zinc-200 dark:border-white/40 cursor-pointer text-xs! p-1! px-2!"
                                        >
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>

        <aside class="w-full min-w-0 shrink-0 basis-full md:basis-0 md:flex-1 md:sticky md:top-4 md:self-start">
            <flux:card size="sm" class="bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <flux:heading size="lg">{{ $this->editingSupplierId ? __('Edit supplier') : __('Add supplier') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($this->editingSupplierId)
                        {{ __('Update vendor master data. Code must be unique within your organization.') }}
                    @else
                        {{ __('Name and status are required. Code, contact, terms, and tax ID are optional.') }}
                    @endif
                </flux:text>
                <flux:separator class="my-5" />
                <form wire:submit="saveSupplier" class="flex flex-col gap-1">
                    <flux:fieldset>
                        <flux:input wire:model="name" :label="__('Name')" type="text" required />
                        <flux:input wire:model="code" :label="__('Code')" type="text" :placeholder="__('Optional internal or ERP code')" />
                        <flux:select wire:model="status" :label="__('Status')" required>
                            @foreach (SupplierStatus::cases() as $case)
                                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="email" :label="__('Email')" type="email" />
                        <flux:input wire:model="phone" :label="__('Phone')" type="text" />
                        <flux:textarea wire:model="address" :label="__('Address')" rows="3" />
                        <flux:input wire:model="payment_terms" :label="__('Payment terms')" type="text" :placeholder="__('e.g. Net 30')" />
                        <flux:input wire:model="tax_id" :label="__('Tax / VAT ID')" type="text" />
                        <flux:textarea wire:model="notes" :label="__('Notes')" rows="3" />
                    </flux:fieldset>
                    <div class="my-4 flex flex-col gap-2 space-y-2">
                        <flux:button variant="primary" type="submit" class="w-full cursor-pointer">
                            {{ $this->editingSupplierId ? __('Update supplier') : __('Save supplier') }}
                        </flux:button>
                        @if ($this->editingSupplierId)
                            <flux:button
                                type="button"
                                variant="ghost"
                                wire:click="cancelEdit"
                                inset="top bottom"
                                class="cursor-pointer text-xs! w-full border border-zinc-200 dark:border-white/40"
                            >
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </form>
            </flux:card>
        </aside>
    </div>
</div>

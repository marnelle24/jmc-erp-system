<?php

namespace App\Livewire\Suppliers;

use App\Domains\Crm\Services\CreateSupplierService;
use App\Domains\Crm\Services\UpdateSupplierService;
use App\Enums\SupplierStatus;
use App\Http\Requests\SupplierPayloadRules;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class SupplierFormModal extends Component
{
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
        $this->status = SupplierStatus::Active->value;
    }

    public function prepareCreate(): void
    {
        Gate::authorize('create', Supplier::class);

        $this->editingSupplierId = null;
        $this->reset('name', 'code', 'email', 'phone', 'address', 'payment_terms', 'tax_id', 'notes');
        $this->status = SupplierStatus::Active->value;
        $this->resetValidation();
    }

    #[On('supplier-form-open-edit')]
    public function openForEdit(int $supplierId): void
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

        $this->modal('supplier-form')->show();
    }

    public function cancel(): void
    {
        $this->editingSupplierId = null;
        $this->reset('name', 'code', 'email', 'phone', 'address', 'payment_terms', 'tax_id', 'notes');
        $this->status = SupplierStatus::Active->value;
        $this->resetValidation();
        $this->modal('supplier-form')->close();
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

            $this->cancel();

            Flux::toast(variant: 'success', text: __('Supplier updated.'));
            $this->dispatch('supplier-saved');

            return;
        }

        Gate::authorize('create', Supplier::class);

        $validated = $this->validate(SupplierPayloadRules::rules());

        $create->execute($tenantId, $validated);

        $this->editingSupplierId = null;
        $this->reset('name', 'code', 'email', 'phone', 'address', 'payment_terms', 'tax_id', 'notes');
        $this->status = SupplierStatus::Active->value;
        $this->resetValidation();

        $this->modal('supplier-form')->close();

        Flux::toast(variant: 'success', text: __('Supplier added.'));
        $this->dispatch('supplier-saved');
    }

    public function render()
    {
        return view('livewire.suppliers.supplier-form-modal');
    }
}

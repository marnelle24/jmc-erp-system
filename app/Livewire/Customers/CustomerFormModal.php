<?php

namespace App\Livewire\Customers;

use App\Domains\Crm\Services\CreateCustomerService;
use App\Domains\Crm\Services\UpdateCustomerService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class CustomerFormModal extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public ?int $editingCustomerId = null;

    public function prepareCreate(): void
    {
        Gate::authorize('create', Customer::class);

        $this->editingCustomerId = null;
        $this->reset('name', 'email', 'phone', 'address');
        $this->resetValidation();
    }

    #[On('customer-form-open-edit')]
    public function openForEdit(int $customerId): void
    {
        $tenantId = (int) session('current_tenant_id');

        $customer = Customer::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($customerId)
            ->firstOrFail();

        Gate::authorize('update', $customer);

        $this->editingCustomerId = $customer->id;
        $this->name = $customer->name;
        $this->email = $customer->email ?? '';
        $this->phone = $customer->phone ?? '';
        $this->address = $customer->address ?? '';
        $this->resetValidation();

        $this->modal('customer-form')->show();
    }

    public function cancel(): void
    {
        $this->editingCustomerId = null;
        $this->reset('name', 'email', 'phone', 'address');
        $this->resetValidation();
        $this->modal('customer-form')->close();
    }

    public function saveCustomer(CreateCustomerService $create, UpdateCustomerService $update): void
    {
        $tenantId = (int) session('current_tenant_id');

        if ($this->editingCustomerId !== null) {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($this->editingCustomerId)
                ->firstOrFail();

            Gate::authorize('update', $customer);

            $validated = $this->validate((new UpdateCustomerRequest)->rules());
            $update->execute($customer, $validated);

            $this->cancel();

            Flux::toast(variant: 'success', text: __('Customer updated.'));
            $this->dispatch('customer-saved');

            return;
        }

        Gate::authorize('create', Customer::class);

        $validated = $this->validate((new StoreCustomerRequest)->rules());
        $create->execute($tenantId, $validated);

        $this->editingCustomerId = null;
        $this->reset('name', 'email', 'phone', 'address');
        $this->resetValidation();

        $this->modal('customer-form')->close();

        Flux::toast(variant: 'success', text: __('Customer added.'));
        $this->dispatch('customer-saved');
    }

    public function render()
    {
        return view('livewire.customers.customer-form-modal');
    }
}

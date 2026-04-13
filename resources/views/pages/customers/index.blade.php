<?php

use App\Domains\Crm\Services\CreateCustomerService;
use App\Domains\Crm\Services\UpdateCustomerService;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app', ['title' => 'Customers'])]
#[Title('Customers')]
class extends Component {
    use WithPagination;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public ?int $editingCustomerId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    public function startEdit(int $customerId): void
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
    }

    public function cancelEdit(): void
    {
        $this->editingCustomerId = null;
        $this->reset('name', 'email', 'phone', 'address');
        $this->resetValidation();
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

            $this->cancelEdit();

            Flux::toast(variant: 'success', text: __('Customer updated.'));

            return;
        }

        Gate::authorize('create', Customer::class);

        $validated = $this->validate((new StoreCustomerRequest)->rules());

        $create->execute($tenantId, $validated);

        $this->reset('name', 'email', 'phone', 'address');
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Customer added.'));
    }

    public function getCustomersProperty()
    {
        $tenantId = (int) session('current_tenant_id');

        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Customers Management') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Manage your customers. Add, edit, and delete customers as needed.') }}</flux:text>
    </div>

    <div class="flex w-full flex-col items-stretch gap-6 md:flex-row md:items-start">
        <div class="min-w-0 w-full basis-full md:basis-0 md:flex-[3]">
            <flux:card class="flex flex-col overflow-hidden p-0 bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <div class="border-b border-zinc-200 px-6 py-5 dark:border-white/10">
                    <flux:heading size="lg">{{ __('Customers Master List') }}</flux:heading>
                    <flux:text class="mt-1 text-sm">{{ __('All customers recorded in the system. Contact details appear on sales documents.') }}</flux:text>
                </div>

                @if ($this->customers->isEmpty())
                    <div class="p-6">
                        <flux:callout icon="users" color="zinc" inline :heading="__('No customers yet')" :text="__('Add a customer using the form beside this list. Email and phone are optional.')" />
                    </div>
                @else
                    <flux:table
                        :paginate="$this->customers->hasPages() ? $this->customers : null"
                        pagination:scroll-to
                    >
                        <flux:table.columns sticky class="bg-neutral-200 dark:bg-neutral-600">
                            <flux:table.column class="px-6!">{{ __('Name') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Email') }}</flux:table.column>
                            <flux:table.column class="px-6!">{{ __('Phone') }}</flux:table.column>
                            <flux:table.column align="end" class="w-0 whitespace-nowrap px-6!">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->customers as $customer)
                                <flux:table.row :key="$customer->id">
                                    <flux:table.cell variant="strong" class="px-6!">{{ $customer->name }}</flux:table.cell>
                                    <flux:table.cell class="px-6!">
                                        @if ($customer->email)
                                            <span class="text-zinc-700 dark:text-zinc-200">{{ $customer->email }}</span>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="px-6!">
                                        @if ($customer->phone)
                                            <span class="tabular-nums text-zinc-700 dark:text-zinc-200">{{ $customer->phone }}</span>
                                        @else
                                            <flux:text class="text-zinc-400">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="px-6!">
                                        <flux:button
                                            type="button"
                                            size="xs"
                                            variant="ghost"
                                            wire:click="startEdit({{ $customer->id }})"
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

        <aside class="w-full min-w-0 shrink-0 basis-full md:basis-0 md:flex-[1] md:sticky md:top-4 md:self-start">
            <flux:card size="sm" class="bg-neutral-100 dark:bg-neutral-700 border border-zinc-300 dark:border-zinc-300/40">
                <flux:heading size="lg">{{ $this->editingCustomerId ? __('Edit customer') : __('Add customer') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($this->editingCustomerId)
                        {{ __('Update name, email, phone, or address for this customer.') }}
                    @else
                        {{ __('Name is required. Email, phone, and address are optional.') }}
                    @endif
                </flux:text>
                <flux:separator class="my-5" />
                <form wire:submit="saveCustomer" class="flex flex-col gap-1">
                    <flux:fieldset>
                        <flux:input wire:model="name" :label="__('Name')" type="text" required />
                        <flux:input wire:model="email" :label="__('Email')" type="email" />
                        <flux:input wire:model="phone" :label="__('Phone')" type="text" />
                        <flux:textarea wire:model="address" :label="__('Address')" rows="3" />
                    </flux:fieldset>
                    <div class="my-4 flex flex-col gap-2 space-y-2">
                        <flux:button variant="primary" type="submit" class="w-full cursor-pointer">
                            {{ $this->editingCustomerId ? __('Update customer') : __('Save customer') }}
                        </flux:button>
                        @if ($this->editingCustomerId)
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

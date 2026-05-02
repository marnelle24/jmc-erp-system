<div class="flex items-center justify-end gap-2">
    @can('create', \App\Models\Customer::class)
        <flux:modal.trigger name="customer-form">
            <flux:button variant="primary" size="sm" icon="plus" class="cursor-pointer" wire:click="prepareCreate">
                {{ __('Add customer') }}
            </flux:button>
        </flux:modal.trigger>
    @endcan

    <flux:modal
        name="customer-form"
        class="[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex flex-col gap-5 p-1">
            <div>
                <flux:heading size="lg">{{ $this->editingCustomerId ? __('Edit customer') : __('Add customer') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($this->editingCustomerId)
                        {{ __('Update name, email, phone, or address for this customer.') }}
                    @else
                        {{ __('Name is required. Email, phone, and address are optional.') }}
                    @endif
                </flux:text>
            </div>

            <form wire:submit="saveCustomer" class="flex flex-col gap-4">
                <flux:fieldset>
                    <flux:input wire:model="name" :label="__('Name')" placeholder="Customer name" type="text" required autofocus />
                    <flux:input wire:model="email" :label="__('Email')" placeholder="Customer email" type="email" />
                    <flux:input wire:model="phone" :label="__('Phone')" placeholder="Customer phone" type="text" />
                    <flux:textarea wire:model="address" :label="__('Address')" placeholder="Customer address" rows="3" />
                </flux:fieldset>
                <div class="flex flex-col gap-2 pt-4 sm:flex-row sm:justify-end">
                    @if ($this->editingCustomerId)
                        <flux:button type="button" variant="filled" wire:click="cancel" class="w-full cursor-pointer sm:w-auto">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif
                    <flux:button variant="primary" type="submit" class="w-full cursor-pointer sm:w-auto">
                        {{ $this->editingCustomerId ? __('Update customer') : __('Save customer') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

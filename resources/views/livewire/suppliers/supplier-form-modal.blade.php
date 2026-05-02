<div class="flex items-center justify-end gap-2">
    @can('create', \App\Models\Supplier::class)
        <flux:modal.trigger name="supplier-form">
            <flux:button variant="primary" size="sm" icon="plus" class="cursor-pointer" wire:click="prepareCreate">
                {{ __('Add supplier') }}
            </flux:button>
        </flux:modal.trigger>
    @endcan

    <flux:modal 
        name="supplier-form" 
        class="[scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex flex-col gap-5 p-1">
            <div>
                <flux:heading size="lg">{{ $this->editingSupplierId ? __('Edit supplier') : __('Add supplier') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    @if ($this->editingSupplierId)
                        {{ __('Update supplier details. Code must be unique within your organization.') }}
                    @else
                        {{ __('Add new supplier with name, code, and status.') }}
                    @endif
                </flux:text>
            </div>

            {{-- <form wire:submit="saveSupplier" class="flex max-h-[min(70vh,36rem)] flex-col gap-4 overflow-y-auto"> --}}
            <form wire:submit="saveSupplier" class="flex flex-col gap-4">
                <flux:fieldset>
                    <flux:input wire:model="name" :label="__('Name')" type="text" placeholder="Supplier name" required autofocus />
                    <div class="grid grid-cols-2 gap-2 my-3">
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Supplier Code') }} <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="code" type="text" placeholder="Supplier code" required />
                        </div>
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Status') }} <span class="text-red-500">*</span></flux:label>
                            <flux:select wire:model="status" required>
                                @foreach (\App\Enums\SupplierStatus::cases() as $case)
                                    <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                    <flux:heading size="lg" class="mt-8 mb-4 font-semibold">{{ __('Contact Information') }}</flux:heading>
                    <flux:input wire:model="email" :label="__('Email')" placeholder="Supplier email" type="email" />
                    <flux:input wire:model="phone" :label="__('Phone')" placeholder="Supplier phone" type="text" />
                    <flux:textarea wire:model="address" :label="__('Address')" placeholder="Supplier address" rows="3" />
                    
                    <flux:heading size="lg" class="mt-8 mb-4 font-semibold">{{ __('Payment Information') }}</flux:heading>
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Payment terms') }}</flux:label>
                            <flux:input wire:model="payment_terms" type="text" :placeholder="__('e.g. Net 30')" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <flux:label>{{ __('Tax / VAT ID') }}</flux:label>
                            <flux:input wire:model="tax_id" type="text" />
                        </div>
                    </div>
                    <flux:textarea wire:model="notes" :label="__('Notes/Remarks')" placeholder="Supplier notes/remarks" rows="3" />
                </flux:fieldset>
                <div class="flex flex-col gap-2 pt-4 sm:flex-row sm:justify-end">
                    @if ($this->editingSupplierId)
                        <flux:button type="button" variant="filled" wire:click="cancel" class="w-full cursor-pointer sm:w-auto">
                            {{ __('Cancel') }}
                        </flux:button>
                    @endif
                    <flux:button variant="primary" type="submit" class="w-full cursor-pointer sm:w-auto">
                        {{ $this->editingSupplierId ? __('Update Supplier') : __('Save Supplier') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>

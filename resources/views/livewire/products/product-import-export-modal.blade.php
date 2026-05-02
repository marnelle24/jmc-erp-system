<div class="flex flex-wrap items-center gap-2">
    <flux:modal.trigger name="product-import-export">
        <flux:button variant="primary" size="sm" icon="arrow-down-tray" class="cursor-pointer" wire:click="clearImportErrors">
            {{ __('Import / Export') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="product-import-export" class="max-w-lg">
        <div class="flex flex-col gap-6 p-1">
            <div>
                <flux:heading size="lg">{{ __('Import & export products') }}</flux:heading>
                <flux:text class="mt-1 text-sm">
                    {{ __('Export downloads all products for your current organization as CSV. Import adds new rows; existing SKUs are skipped.') }}
                </flux:text>
            </div>

            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <flux:heading size="sm">{{ __('Export') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Columns: name, sku, description, categories (UTF-8, Excel-friendly). Categories use pipe | between names.') }}
                </flux:text>
                <div>
                    <flux:button type="button" variant="primary" size="sm" wire:click="export" class="cursor-pointer">
                        {{ __('Download CSV') }}
                    </flux:button>
                </div>
            </div>

            <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <flux:heading size="sm">{{ __('Import') }}</flux:heading>
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Use a header row with columns name, sku, description, and optional categories (any order). If the first row is not a header, columns are read as name, sku, description, categories.') }}
                </flux:text>

                @can('create', \App\Models\Product::class)
                    <div class="flex flex-col gap-3">
                        <flux:field>
                            <flux:label>{{ __('CSV file') }}</flux:label>
                            <input
                                type="file"
                                wire:model="csv"
                                accept=".csv,.txt,text/csv,text/plain"
                                class="block w-full border border-zinc-200 rounded-md text-sm text-zinc-600 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-900 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-white/10 dark:file:text-white dark:hover:file:bg-white/20"
                            />
                            <flux:error name="csv" />
                        </flux:field>

                        <div wire:loading wire:target="csv" class="text-sm text-zinc-500">
                            {{ __('Uploading…') }}
                        </div>

                        @if ($importErrors !== [])
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-500/30 dark:bg-amber-500/10">
                                <p class="font-medium text-amber-950 dark:text-amber-100">{{ __('Issues on some rows') }}</p>
                                <ul class="mt-2 max-h-48 list-inside list-disc space-y-1 overflow-y-auto text-amber-900 dark:text-amber-200">
                                    @foreach (array_slice($importErrors, 0, 25) as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                                @if (count($importErrors) > 25)
                                    <flux:text class="mt-2 text-sm">
                                        {{ __('Showing first 25 messages.') }}
                                    </flux:text>
                                @endif
                            </div>
                        @endif

                        <flux:button type="button" variant="primary" size="sm" wire:click="import" wire:loading.attr="disabled" class="w-fit cursor-pointer">
                            <span wire:loading.remove wire:target="import">{{ __('Import CSV') }}</span>
                            <span wire:loading wire:target="import">{{ __('Importing…') }}</span>
                        </flux:button>
                    </div>
                @else
                    <flux:text class="text-sm text-zinc-500">{{ __('You do not have permission to import products.') }}</flux:text>
                @endcan
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost" size="sm" class="cursor-pointer">{{ __('Close') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>

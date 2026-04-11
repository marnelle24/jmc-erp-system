@props([
    'sidebar' => false,
])

@php
    $organizationName = once(function (): string {
        if (! auth()->check()) {
            return (string) config('app.name');
        }

        $tenantId = session('current_tenant_id');
        if ($tenantId === null) {
            return (string) config('app.name');
        }

        return \App\Models\Tenant::query()
            ->whereKey((int) $tenantId)
            ->value('name') ?? (string) config('app.name');
    });

    $organizationName = \Illuminate\Support\Str::words($organizationName, 3, '…');
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$organizationName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$organizationName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif

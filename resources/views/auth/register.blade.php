<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-zinc-100">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6">
        <section class="w-full rounded-xl bg-white p-6 shadow-sm">
            <h1 class="mb-1 text-xl font-semibold text-zinc-900">Create account</h1>
            <p class="mb-6 text-sm text-zinc-600">Register a new user account.</p>
            <x-auth.register-form />
        </section>
    </main>
    @livewireScripts
</body>
</html>

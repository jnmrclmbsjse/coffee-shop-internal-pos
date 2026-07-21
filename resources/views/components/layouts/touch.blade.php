<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'UCM Coffee Studio' }} · UCM Coffee Studio</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-100 text-stone-900 antialiased">
    <div class="flex min-h-full flex-col">
        {{-- Touch-first top bar: brand + primary inventory navigation. --}}
        <header class="sticky top-0 z-10 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div class="flex items-center gap-4 px-5 py-3">
                <span class="text-lg font-semibold tracking-tight text-amber-700">UCM&nbsp;Coffee&nbsp;Studio</span>
                <nav class="flex flex-1 items-center gap-1 overflow-x-auto">
                    {{-- Shared touch nav across POS + Inventory. Only list routes that exist;
                         add POS tabs (order taking, cash, close) as sub-phases 3b–3d land. --}}
                    @php($tabs = [
                        'pos.open' => 'Open Day',
                        'pos.order' => 'Take Order',
                        'pos.cash' => 'Cash & Expenses',
                        'pos.close' => 'Close Day',
                        'inventory.opening' => 'Opening',
                        'inventory.closing' => 'Closing',
                        'inventory.status' => 'Restock',
                        'inventory.movements' => 'Deliveries & Wastage',
                    ])
                    @foreach ($tabs as $route => $label)
                        <a href="{{ route($route) }}"
                           wire:navigate
                           @class([
                               'whitespace-nowrap rounded-lg px-4 py-2 text-base font-medium transition',
                               'bg-amber-600 text-white' => request()->routeIs($route),
                               'text-stone-600 hover:bg-stone-100' => ! request()->routeIs($route),
                           ])>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </header>

        <main class="flex-1 px-5 py-6">
            {{ $slot }}
        </main>
    </div>
    @livewireScriptConfig
</body>
</html>

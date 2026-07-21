@php($order = $this->order)
<div class="mx-auto max-w-7xl space-y-4">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Take order</h1>
            <p class="text-stone-500">Tap a size to add it. Totals update automatically.</p>
        </div>
        @if ($this->businessDay)
            <div class="text-right">
                <div class="text-lg font-semibold">{{ $this->businessDay->business_date->format('D, M j Y') }}</div>
                <div class="text-sm text-stone-500">{{ ucfirst($this->businessDay->day_type->value) }} day</div>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-600 px-4 py-3 font-medium text-white">{{ session('status') }}</div>
    @endif

    @unless ($this->businessDay)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            No business day is open. <a href="{{ route('pos.open') }}" wire:navigate class="font-semibold text-amber-700">Open one first.</a>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
            {{-- ============ PRODUCT GRID ============ --}}
            <div class="space-y-4 lg:col-span-3">
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->categories as $category)
                        <button type="button" wire:click="selectCategory('{{ $category->id }}')"
                                @class([
                                    'rounded-lg px-4 py-2 text-base font-semibold transition',
                                    'bg-amber-600 text-white' => $activeCategoryId === $category->id,
                                    'bg-white text-stone-700 hover:bg-stone-100' => $activeCategoryId !== $category->id,
                                ])>
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @forelse ($this->products as $product)
                        <div class="rounded-xl border border-stone-200 bg-white p-4" wire:key="prod-{{ $product->id }}">
                            <div class="font-semibold">{{ $product->name }}</div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($product->sizes as $size)
                                    <button type="button" wire:click="addItem('{{ $size->id }}')"
                                            class="flex-1 rounded-lg bg-stone-100 px-3 py-3 text-left hover:bg-amber-100">
                                        <span class="block text-base font-semibold">{{ $size->label }}</span>
                                        <span class="block text-sm text-stone-500">₱{{ number_format((float) $size->price, 2) }}</span>
                                    </button>
                                @empty
                                    <span class="text-sm text-stone-400">No sizes configured.</span>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
                            No products in this category.
                        </div>
                    @endforelse
                </div>

                {{-- Parked orders to resume --}}
                @if ($this->parkedOrders->isNotEmpty())
                    <div class="rounded-xl border border-stone-200 bg-white p-4">
                        <div class="mb-2 text-sm font-semibold uppercase tracking-wide text-stone-500">Parked orders</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->parkedOrders as $parked)
                                <button type="button" wire:click="resumeOrder('{{ $parked->id }}')"
                                        @class([
                                            'rounded-lg px-4 py-2 text-left',
                                            'bg-amber-600 text-white' => $order && $order->id === $parked->id,
                                            'bg-amber-50 text-amber-900 hover:bg-amber-100' => ! ($order && $order->id === $parked->id),
                                        ])>
                                    <span class="block font-semibold">#{{ $parked->order_number }} {{ $parked->customer_name ?: 'Walk-in' }}</span>
                                    <span class="block text-xs">{{ $parked->items_count }} item(s) · ₱{{ number_format((float) $parked->total, 2) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ============ CURRENT ORDER ============ --}}
            <div class="lg:col-span-2">
                <div class="sticky top-20 rounded-xl border border-stone-200 bg-white">
                    <div class="border-b border-stone-100 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold">
                                {{ $order ? '#'.$order->order_number : 'New order' }}
                            </span>
                            <div class="flex gap-1">
                                @foreach (\App\Enums\ServiceType::cases() as $service)
                                    <button type="button" wire:click="setService('{{ $service->value }}')"
                                            @class([
                                                'rounded-lg px-3 py-1.5 text-sm font-semibold',
                                                'bg-amber-600 text-white' => $order && $order->service_type === $service,
                                                'bg-stone-100 text-stone-600' => ! ($order && $order->service_type === $service),
                                            ])>
                                        {{ $service->getLabel() }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <input type="text" wire:model.blur="customerName"
                               class="mt-3 w-full rounded-lg border-stone-300 py-2 text-base"
                               placeholder="Customer name (optional)">
                    </div>

                    <div class="divide-y divide-stone-100">
                        @forelse (($order?->items ?? collect()) as $item)
                            <div class="p-4" wire:key="line-{{ $item->id }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold">{{ $item->productSize->product->name }} · {{ $item->productSize->label }}</div>
                                        <div class="text-sm text-stone-500">₱{{ number_format((float) $item->unit_price, 2) }} each</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold">₱{{ number_format((float) $item->line_total, 2) }}</div>
                                        @if ($item->discount_type !== \App\Enums\DiscountType::None)
                                            <div class="text-xs text-emerald-700">−₱{{ number_format((float) $item->discount_amount, 2) }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="decrementQty('{{ $item->id }}')"
                                                class="h-9 w-9 rounded-lg bg-stone-100 text-xl font-bold leading-none text-stone-700">−</button>
                                        <span class="w-8 text-center text-lg font-semibold">{{ $item->quantity }}</span>
                                        <button type="button" wire:click="incrementQty('{{ $item->id }}')"
                                                class="h-9 w-9 rounded-lg bg-stone-100 text-xl font-bold leading-none text-stone-700">+</button>
                                    </div>
                                    <div class="flex gap-1">
                                        @foreach (\App\Enums\DiscountType::cases() as $discount)
                                            <button type="button" wire:click="setDiscount('{{ $item->id }}', '{{ $discount->value }}')"
                                                    @class([
                                                        'rounded-md px-2.5 py-1.5 text-xs font-semibold',
                                                        'bg-emerald-600 text-white' => $item->discount_type === $discount,
                                                        'bg-stone-100 text-stone-600' => $item->discount_type !== $discount,
                                                    ])>
                                                {{ $discount->getLabel() }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <input type="text"
                                       value="{{ $item->taste_preference }}"
                                       wire:change="saveTaste('{{ $item->id }}', $event.target.value)"
                                       class="mt-2 w-full rounded-lg border-stone-200 py-2 text-sm"
                                       placeholder="Taste preference (e.g. less ice)">
                            </div>
                        @empty
                            <div class="p-10 text-center text-stone-400">No items yet — tap a size to start.</div>
                        @endforelse
                    </div>

                    <div class="space-y-3 border-t border-stone-100 p-4">
                        <div class="flex justify-between text-sm text-stone-500">
                            <span>Subtotal</span><span>₱{{ number_format((float) ($order->subtotal ?? 0), 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm text-stone-500">
                            <span>Discount</span><span>−₱{{ number_format((float) ($order->discount_amount ?? 0), 2) }}</span>
                        </div>
                        <div class="flex justify-between text-2xl font-bold">
                            <span>Total</span><span>₱{{ number_format((float) ($order->total ?? 0), 2) }}</span>
                        </div>
                        @error('order') <div class="text-sm text-red-600">{{ $message }}</div> @enderror

                        @if ($confirmingVoid)
                            <div class="space-y-2 rounded-lg bg-red-50 p-3">
                                <input type="text" wire:model="voidReason"
                                       class="w-full rounded-lg border-red-300 py-2 text-sm"
                                       placeholder="Reason for void">
                                @error('voidReason') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                                <div class="flex gap-2">
                                    <button type="button" wire:click="voidOrder"
                                            class="flex-1 rounded-lg bg-red-600 px-4 py-2 font-semibold text-white">Confirm void</button>
                                    <button type="button" wire:click="$set('confirmingVoid', false)"
                                            class="rounded-lg bg-stone-100 px-4 py-2 font-semibold text-stone-600">Cancel</button>
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="completeOrder('cash')"
                                        @disabled(! $order || $order->items->isEmpty())
                                        class="rounded-xl bg-emerald-600 px-4 py-4 text-lg font-semibold text-white disabled:opacity-40">
                                    Cash
                                </button>
                                <button type="button" wire:click="completeOrder('online')"
                                        @disabled(! $order || $order->items->isEmpty())
                                        class="rounded-xl bg-emerald-600 px-4 py-4 text-lg font-semibold text-white disabled:opacity-40">
                                    Online
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" wire:click="park"
                                        @disabled(! $order)
                                        class="rounded-xl bg-amber-100 px-4 py-3 font-semibold text-amber-800 disabled:opacity-40">
                                    Park
                                </button>
                                <button type="button" wire:click="$set('confirmingVoid', true)"
                                        @disabled(! $order)
                                        class="rounded-xl bg-stone-100 px-4 py-3 font-semibold text-red-700 disabled:opacity-40">
                                    Void
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endunless
</div>

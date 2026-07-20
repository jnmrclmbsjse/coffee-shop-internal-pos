<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Deliveries &amp; wastage</h1>
            <p class="text-stone-500">Adjust stock between counts. Each entry is permanent.</p>
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
            No business day is open.
        </div>
    @else
        <form wire:submit="record" class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium text-stone-600">Item <span class="text-red-500">*</span></span>
                <select wire:model="stockItemId" class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                    <option value="">Select item…</option>
                    @foreach ($this->items as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}{{ $item->size ? ' · '.$item->size : '' }}</option>
                    @endforeach
                </select>
                @error('stockItemId') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Type <span class="text-red-500">*</span></span>
                <div class="mt-1 flex gap-2">
                    @foreach (\App\Enums\StockMovementType::cases() as $movementType)
                        <button type="button"
                                wire:click="$set('type', '{{ $movementType->value }}')"
                                @class([
                                    'flex-1 rounded-lg px-4 py-3 text-lg font-semibold',
                                    'bg-amber-600 text-white' => $type === $movementType,
                                    'bg-stone-100 text-stone-700' => $type !== $movementType,
                                ])>
                            {{ $movementType->getLabel() }}
                        </button>
                    @endforeach
                </div>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Quantity <span class="text-red-500">*</span></span>
                <input type="number" min="0" step="any" inputmode="numeric"
                       wire:model="quantity"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg" placeholder="0">
                @error('quantity') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Recorded by</span>
                <select wire:model="recordedBy" class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                    <option value="">—</option>
                    @foreach ($this->staff as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium text-stone-600">Reason</span>
                <input type="text" wire:model="reason"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg"
                       placeholder="e.g. AM delivery, dropped tray">
            </label>

            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-amber-600 px-8 py-4 text-lg font-semibold text-white shadow-sm hover:bg-amber-700">
                    Record movement
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left">
                <thead class="bg-stone-50 text-sm uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-5 py-3">Item</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Qty</th>
                        <th class="px-5 py-3">Reason</th>
                        <th class="px-5 py-3">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($this->movements as $movement)
                        <tr wire:key="mv-{{ $movement->id }}">
                            <td class="px-5 py-3 font-medium">{{ $movement->stockItem->name }}</td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-3 py-1 text-sm font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $movement->type === \App\Enums\StockMovementType::Delivery,
                                    'bg-red-100 text-red-800' => $movement->type === \App\Enums\StockMovementType::Wastage,
                                ])>{{ $movement->type->getLabel() }}</span>
                            </td>
                            <td class="px-5 py-3">{{ rtrim(rtrim((string) $movement->quantity, '0'), '.') }}</td>
                            <td class="px-5 py-3 text-stone-500">{{ $movement->reason ?: '—' }}</td>
                            <td class="px-5 py-3 text-stone-500">{{ $movement->createdBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-stone-500">No movements recorded today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>

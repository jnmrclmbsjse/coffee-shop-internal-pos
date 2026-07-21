<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Open business day</h1>
        <p class="text-stone-500">Start the day everything else anchors on — orders, cash, and counts.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-600 px-4 py-3 font-medium text-white">{{ session('status') }}</div>
    @endif

    @if ($this->businessDay)
        {{-- A day is already open: show its summary. Closing is sub-phase 3d. --}}
        <div class="rounded-xl border border-stone-200 bg-white p-8 text-center">
            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1 text-sm font-semibold text-emerald-800">
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Day open
            </div>
            <div class="mt-4 text-3xl font-bold">{{ $this->businessDay->business_date->format('l, M j Y') }}</div>
            <div class="mt-1 text-stone-500">{{ ucfirst($this->businessDay->day_type->value) }} day</div>
            <dl class="mx-auto mt-6 grid max-w-sm grid-cols-2 gap-3 text-left">
                <dt class="text-stone-500">Cash float</dt>
                <dd class="text-right font-semibold">₱{{ number_format((float) $this->businessDay->cash_float, 2) }}</dd>
                <dt class="text-stone-500">Opened by</dt>
                <dd class="text-right font-semibold">{{ $this->businessDay->openedBy?->name ?? '—' }}</dd>
                <dt class="text-stone-500">Opened at</dt>
                <dd class="text-right font-semibold">{{ $this->businessDay->opened_at?->format('g:i A') ?? '—' }}</dd>
            </dl>
            <p class="mt-6 text-sm text-stone-500">Take orders and record cash against this day; close it at end of shift.</p>
        </div>
    @else
        <form wire:submit="open" class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium text-stone-600">Business date <span class="text-red-500">*</span></span>
                <input type="date" wire:model="businessDate"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                @error('businessDate') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Day type <span class="text-red-500">*</span></span>
                <div class="mt-1 flex gap-2">
                    @foreach (\App\Enums\DayType::cases() as $type)
                        <button type="button"
                                wire:click="$set('dayType', '{{ $type->value }}')"
                                @class([
                                    'flex-1 rounded-lg px-4 py-3 text-lg font-semibold',
                                    'bg-amber-600 text-white' => $dayType === $type,
                                    'bg-stone-100 text-stone-700' => $dayType !== $type,
                                ])>
                            {{ $type->getLabel() }}
                        </button>
                    @endforeach
                </div>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Cash float <span class="text-red-500">*</span></span>
                <input type="number" min="0" step="0.01" inputmode="decimal"
                       wire:model="cashFloat"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg" placeholder="0.00">
                @error('cashFloat') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Opened by</span>
                <select wire:model="openedBy" class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                    <option value="">—</option>
                    @foreach ($this->staff as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="rounded-xl bg-amber-600 px-8 py-4 text-lg font-semibold text-white shadow-sm hover:bg-amber-700">
                    Open day
                </button>
            </div>
        </form>
    @endif
</div>

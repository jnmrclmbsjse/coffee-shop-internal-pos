@php($statusStyles = [
    'urgent' => 'bg-red-100 text-red-800',
    'low' => 'bg-amber-100 text-amber-800',
    'below_par' => 'bg-sky-100 text-sky-800',
    'enough' => 'bg-emerald-100 text-emerald-800',
])

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ $this->phaseEnum->getLabel() }} count</h1>
            <p class="text-stone-500">
                {{ $this->phaseEnum === \App\Enums\CountPhase::Opening ? 'Short sheet — critical items.' : 'Full sheet — every active item.' }}
            </p>
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
    @if (session('error'))
        <div class="rounded-lg bg-red-600 px-4 py-3 font-medium text-white">{{ session('error') }}</div>
    @endif

    @unless ($this->businessDay)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            No business day is open. A day must be opened before counting.
        </div>
    @elseif ($this->existingCount)
        {{-- Submitted: append-only, so show the recorded sheet read-only. --}}
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-medium text-emerald-800">
            Submitted{{ $this->existingCount->submitted_at ? ' at '.$this->existingCount->submitted_at->format('g:i A') : '' }}.
        </div>
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left">
                <thead class="bg-stone-50 text-sm uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-5 py-3">Item</th>
                        <th class="px-5 py-3">Counted</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($this->existingCount->lines as $line)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $line->stockItem->name }}</td>
                            <td class="px-5 py-3">
                                {{ $line->counted_qty !== null ? (int) $line->counted_qty : $line->counted_level?->getLabel() }}
                            </td>
                            <td class="px-5 py-3">
                                @if ($line->computed_status)
                                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusStyles[$line->computed_status->value] ?? 'bg-stone-100 text-stone-700' }}">
                                        {{ $line->computed_status->getLabel() }}
                                    </span>
                                @else
                                    <span class="text-stone-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <form wire:submit="submit" class="space-y-6">
            {{-- Who counted --}}
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-stone-600">Submitted by <span class="text-red-500">*</span></span>
                    <select wire:model="submittedBy" class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                        <option value="">Select staff…</option>
                        @foreach ($this->staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                    @error('submittedBy') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-stone-600">Shift lead</span>
                    <select wire:model="shiftLead" class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                        <option value="">—</option>
                        @foreach ($this->staff as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            {{-- Items --}}
            <div class="space-y-3">
                @foreach ($this->items as $item)
                    <div class="rounded-xl border border-stone-200 bg-white p-4" wire:key="item-{{ $item->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <div class="text-lg font-semibold">{{ $item->name }}</div>
                                <div class="text-sm text-stone-500">
                                    {{ $item->size ? $item->size.' · ' : '' }}{{ $item->unit }}
                                    @if ($item->is_critical)
                                        <span class="ml-1 rounded bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">critical</span>
                                    @endif
                                </div>
                            </div>

                            @if ($item->count_method === \App\Enums\StockCountMethod::Quantity)
                                <input type="number" min="0" inputmode="numeric"
                                       wire:model="counts.{{ $item->id }}.qty"
                                       class="w-32 rounded-lg border-stone-300 py-3 text-center text-xl font-semibold"
                                       placeholder="0">
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->levels() as $level)
                                        <button type="button"
                                                wire:click="$set('counts.{{ $item->id }}.level', '{{ $level->value }}')"
                                                @class([
                                                    'rounded-lg px-3 py-2 text-sm font-semibold',
                                                    'bg-amber-600 text-white' => ($counts[$item->id]['level'] ?? null) === $level->value,
                                                    'bg-stone-100 text-stone-700' => ($counts[$item->id]['level'] ?? null) !== $level->value,
                                                ])>
                                            {{ $level->getLabel() }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        @error("counts.{$item->id}.qty") <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error("counts.{$item->id}.level") <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach

                @if ($this->items->isEmpty())
                    <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
                        No {{ $this->phaseEnum === \App\Enums\CountPhase::Opening ? 'critical ' : '' }}items to count.
                    </div>
                @endif
            </div>

            @if ($this->items->isNotEmpty())
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-xl bg-amber-600 px-8 py-4 text-lg font-semibold text-white shadow-sm hover:bg-amber-700">
                        Submit {{ strtolower($this->phaseEnum->getLabel()) }} count
                    </button>
                </div>
            @endif
        </form>
    @endunless
</div>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Cash &amp; expenses</h1>
            <p class="text-stone-500">Drawer cash in/out and expenses. Each entry is permanent.</p>
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
        <form wire:submit="record" class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <span class="text-sm font-medium text-stone-600">Type <span class="text-red-500">*</span></span>
                <div class="mt-1 flex gap-2">
                    @php($kinds = ['cash_in' => 'Cash in', 'cash_out' => 'Cash out', 'expense' => 'Expense'])
                    @foreach ($kinds as $value => $label)
                        <button type="button" wire:click="$set('kind', '{{ $value }}')"
                                @class([
                                    'flex-1 rounded-lg px-4 py-3 text-lg font-semibold',
                                    'bg-amber-600 text-white' => $kind === $value,
                                    'bg-stone-100 text-stone-700' => $kind !== $value,
                                ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Amount <span class="text-red-500">*</span></span>
                <input type="number" min="0" step="0.01" inputmode="decimal"
                       wire:model="amount"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg" placeholder="0.00">
                @error('amount') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            @if ($kind === 'expense')
                <label class="block">
                    <span class="text-sm font-medium text-stone-600">Category</span>
                    <input type="text" wire:model="category"
                           class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg"
                           placeholder="e.g. supplies, transport">
                </label>
            @else
                <div class="hidden sm:block"></div>
            @endif

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium text-stone-600">Reason <span class="text-red-500">*</span></span>
                <input type="text" wire:model="reason"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg"
                       placeholder="e.g. bank change, milk delivery payment">
                @error('reason') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
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

            <div class="flex items-end justify-end">
                <button type="submit" class="rounded-xl bg-amber-600 px-8 py-4 text-lg font-semibold text-white shadow-sm hover:bg-amber-700">
                    Record
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left">
                <thead class="bg-stone-50 text-sm uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Detail</th>
                        <th class="px-5 py-3">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($this->entries as $entry)
                        <tr wire:key="entry-{{ $entry['id'] }}">
                            <td class="px-5 py-3">
                                <span @class([
                                    'rounded-full px-3 py-1 text-sm font-semibold',
                                    'bg-emerald-100 text-emerald-800' => ! $entry['is_out'],
                                    'bg-red-100 text-red-800' => $entry['is_out'],
                                ])>{{ $entry['label'] }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold {{ $entry['is_out'] ? 'text-red-700' : 'text-emerald-700' }}">
                                {{ $entry['is_out'] ? '−' : '+' }}₱{{ number_format($entry['amount'], 2) }}
                            </td>
                            <td class="px-5 py-3 text-stone-500">{{ $entry['detail'] ?: '—' }}</td>
                            <td class="px-5 py-3 text-stone-500">{{ $entry['by'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-stone-500">Nothing recorded today.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>

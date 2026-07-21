@php($summary = $this->cashSummary)
<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Close business day</h1>
        <p class="text-stone-500">Review both reconciliations, count the drawer, then close.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-emerald-600 px-4 py-3 font-medium text-white">{{ session('status') }}</div>
    @endif

    {{-- ============ CLOSED RESULT ============ --}}
    @if ($this->closedDay)
        @php($d = $this->closedDay)
        <div class="rounded-xl border border-stone-200 bg-white p-8">
            <div class="inline-flex items-center gap-2 rounded-full bg-stone-800 px-4 py-1 text-sm font-semibold text-white">
                Day closed
            </div>
            <div class="mt-4 text-2xl font-bold">{{ $d->business_date->format('l, M j Y') }}</div>
            <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div><dt class="text-stone-500">Expected cash</dt><dd class="text-lg font-semibold">₱{{ number_format((float) $d->expected_cash, 2) }}</dd></div>
                <div><dt class="text-stone-500">Actual cash</dt><dd class="text-lg font-semibold">₱{{ number_format((float) $d->actual_cash, 2) }}</dd></div>
                <div>
                    <dt class="text-stone-500">Discrepancy</dt>
                    <dd class="text-lg font-semibold {{ (float) $d->cash_discrepancy < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                        ₱{{ number_format((float) $d->cash_discrepancy, 2) }}
                    </dd>
                </div>
            </dl>
            @if ($d->discrepancy_reason)
                <p class="mt-4 text-sm text-stone-500">Reason: {{ $d->discrepancy_reason }}</p>
            @endif
        </div>
    @elseif (! $this->businessDay)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            No business day is open.
        </div>
    @else
        @unless ($this->hasClosingCount)
            <div class="rounded-lg bg-amber-100 px-4 py-3 font-medium text-amber-800">
                No closing count submitted yet — cup/lid variances won't be snapshotted.
                <a href="{{ route('inventory.closing') }}" wire:navigate class="font-semibold underline">Do the closing count.</a>
            </div>
        @endunless

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- ============ CUP / LID BALANCE ============ --}}
            <div class="rounded-xl border border-stone-200 bg-white">
                <div class="border-b border-stone-100 px-5 py-3 font-semibold">Cup / lid balance</div>
                <table class="w-full text-left text-sm">
                    <thead class="bg-stone-50 uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-4 py-2">Item</th>
                            <th class="px-4 py-2 text-right">Expected</th>
                            <th class="px-4 py-2 text-right">Actual</th>
                            <th class="px-4 py-2 text-right">Var</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($this->cupBalance as $row)
                            <tr wire:key="cup-{{ $row->stock_item_id }}">
                                <td class="px-4 py-2 font-medium">{{ $row->item_name }}{{ $row->size ? ' · '.$row->size : '' }}</td>
                                <td class="px-4 py-2 text-right">{{ (int) $row->expected_close }}</td>
                                <td class="px-4 py-2 text-right">{{ $row->actual_close === null ? '—' : (int) $row->actual_close }}</td>
                                <td class="px-4 py-2 text-right font-semibold {{ $row->variance < 0 ? 'text-red-700' : 'text-stone-700' }}">
                                    {{ $row->variance === null ? '—' : (int) $row->variance }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-stone-500">No reconciled items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ============ CASH SUMMARY ============ --}}
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <div class="mb-3 font-semibold">Cash summary <span class="text-sm font-normal text-stone-400">(online sales excluded)</span></div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">Cash float</dt><dd>₱{{ number_format((float) ($summary->cash_float ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Cash sales</dt><dd>₱{{ number_format((float) ($summary->cash_sales ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between text-stone-400"><dt>Online sales (excluded)</dt><dd>₱{{ number_format((float) ($summary->online_sales ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Cash in</dt><dd>+₱{{ number_format((float) ($summary->total_cash_in ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Cash out</dt><dd>−₱{{ number_format((float) ($summary->total_cash_out ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">Expenses</dt><dd>−₱{{ number_format((float) ($summary->total_expenses ?? 0), 2) }}</dd></div>
                    <div class="flex justify-between border-t border-stone-100 pt-2 text-base font-bold">
                        <dt>Expected cash</dt><dd>₱{{ number_format($this->expectedCash, 2) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- ============ CLOSE FORM ============ --}}
        <form wire:submit="close" class="grid grid-cols-1 gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
            <label class="block">
                <span class="text-sm font-medium text-stone-600">Actual cash counted <span class="text-red-500">*</span></span>
                <input type="number" min="0" step="0.01" inputmode="decimal"
                       wire:model.live="actualCash"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg" placeholder="0.00">
                @error('actualCash') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="block">
                <span class="text-sm font-medium text-stone-600">Discrepancy</span>
                <div class="mt-1 rounded-lg bg-stone-50 py-3 px-4 text-lg font-semibold
                            {{ $this->discrepancy === null ? 'text-stone-400' : ($this->discrepancy < 0 ? 'text-red-700' : 'text-emerald-700') }}">
                    {{ $this->discrepancy === null ? '—' : '₱'.number_format($this->discrepancy, 2) }}
                </div>
            </div>

            <label class="block sm:col-span-2">
                <span class="text-sm font-medium text-stone-600">Discrepancy reason</span>
                <input type="text" wire:model="discrepancyReason"
                       class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg"
                       placeholder="e.g. short by change given, over from tips">
            </label>

            <label class="block">
                <span class="text-sm font-medium text-stone-600">Closed by</span>
                <select wire:model="closedBy" class="mt-1 w-full rounded-lg border-stone-300 py-3 text-lg">
                    <option value="">—</option>
                    @foreach ($this->staff as $person)
                        <option value="{{ $person->id }}">{{ $person->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="flex items-end justify-end">
                <button type="submit"
                        wire:confirm="Close the day? This is final."
                        class="rounded-xl bg-stone-800 px-8 py-4 text-lg font-semibold text-white shadow-sm hover:bg-stone-900">
                    Close day
                </button>
            </div>
        </form>
    @endif
</div>

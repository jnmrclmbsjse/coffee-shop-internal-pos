@php($statusStyles = [
    'urgent' => 'bg-red-100 text-red-800',
    'low' => 'bg-amber-100 text-amber-800',
    'below_par' => 'bg-sky-100 text-sky-800',
    'enough' => 'bg-emerald-100 text-emerald-800',
])
@php($levelLabels = collect(\App\Enums\StockLevel::cases())->mapWithKeys(fn ($l) => [$l->value => $l->getLabel()]))

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Restock status</h1>
            <p class="text-stone-500">Counts vs par for the day. Restock the top of the list first.</p>
        </div>
        @if ($this->businessDay && $this->phase)
            <div class="text-right">
                <div class="text-lg font-semibold">{{ $this->businessDay->business_date->format('D, M j Y') }}</div>
                <div class="text-sm text-stone-500">{{ $this->phase->getLabel() }} count</div>
            </div>
        @endif
    </div>

    @unless ($this->businessDay)
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center text-stone-500">
            No business day is open.
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left">
                <thead class="bg-stone-50 text-sm uppercase tracking-wide text-stone-500">
                    <tr>
                        <th class="px-5 py-3">Item</th>
                        <th class="px-5 py-3">Counted</th>
                        <th class="px-5 py-3">Par</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @forelse ($this->rows as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">
                                {{ $row->item_name }}
                                @if ($row->size)<span class="text-stone-400"> · {{ $row->size }}</span>@endif
                            </td>
                            <td class="px-5 py-3">
                                {{ $row->counted_qty !== null ? (int) $row->counted_qty : ($levelLabels[$row->counted_level] ?? '—') }}
                            </td>
                            <td class="px-5 py-3 text-stone-500">
                                {{ $row->par_qty !== null ? (int) $row->par_qty : ($levelLabels[$row->par_level_value] ?? '—') }}
                            </td>
                            <td class="px-5 py-3">
                                @if ($row->computed_status)
                                    <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $statusStyles[$row->computed_status] ?? 'bg-stone-100 text-stone-700' }}">
                                        {{ \App\Enums\ParStatus::from($row->computed_status)->getLabel() }}
                                    </span>
                                @else
                                    <span class="text-stone-400">no par</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-10 text-center text-stone-500">
                                No count has been submitted for this day yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endunless
</div>

<?php

namespace App\Livewire\Pos;

use App\Enums\CashMovementType;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Record cash in/out and expenses against the open day (Module 3c). All feed the
 * cash reconciliation: expected_cash = float + cash_sales + cash_in − cash_out −
 * expenses (online sales excluded — see v_daily_cash_summary). Append-only, with a
 * combined same-day log. Mirrors the Module 2 Movements screen.
 */
#[Layout('components.layouts.touch')]
class CashLog extends Component
{
    use ResolvesBusinessDay;

    /** One of: cash_in, cash_out, expense. */
    public string $kind = 'cash_in';

    public ?string $amount = null;

    public ?string $reason = null;

    public ?string $category = null;

    public ?string $recordedBy = null;

    /**
     * @return Collection<int, Staff>
     */
    #[Computed]
    public function staff(): Collection
    {
        return Staff::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Cash movements + expenses for the day, merged newest-first for the log.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function entries(): Collection
    {
        if (! $this->businessDay) {
            return collect();
        }

        $movements = CashMovement::query()
            ->where('business_day_id', $this->businessDay->id)
            ->with('createdBy')
            ->get()
            ->map(fn (CashMovement $m) => [
                'id' => $m->id,
                'label' => $m->type->getLabel(),
                'is_out' => $m->type === CashMovementType::CashOut,
                'amount' => (float) $m->amount,
                'detail' => $m->reason,
                'by' => $m->createdBy?->name,
                'at' => $m->created_at,
            ]);

        $expenses = Expense::query()
            ->where('business_day_id', $this->businessDay->id)
            ->with('createdBy')
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'label' => 'Expense',
                'is_out' => true,
                'amount' => (float) $e->amount,
                'detail' => trim(($e->category ? $e->category.' — ' : '').$e->reason),
                'by' => $e->createdBy?->name,
                'at' => $e->created_at,
            ]);

        return $movements->concat($expenses)->sortByDesc('at')->values();
    }

    public function record(): void
    {
        if (! $this->businessDay) {
            return;
        }

        $this->validate([
            'kind' => 'required|in:cash_in,cash_out,expense',
            'amount' => 'required|numeric|gt:0',
            'reason' => 'required|string',
            'category' => 'nullable|string',
        ]);

        if ($this->kind === 'expense') {
            Expense::create([
                'business_day_id' => $this->businessDay->id,
                'amount' => $this->amount,
                'category' => $this->category ?: null,
                'reason' => $this->reason,
                'created_by' => $this->recordedBy,
            ]);
        } else {
            CashMovement::create([
                'business_day_id' => $this->businessDay->id,
                'type' => CashMovementType::from($this->kind),
                'amount' => $this->amount,
                'reason' => $this->reason,
                'created_by' => $this->recordedBy,
            ]);
        }

        $this->reset(['amount', 'reason', 'category']);
        unset($this->entries);
        session()->flash('status', 'Recorded.');
    }

    public function render()
    {
        return view('livewire.pos.cash-log');
    }
}

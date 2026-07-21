<?php

namespace App\Livewire\Pos;

use App\Enums\CountPhase;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\BusinessDay;
use App\Models\Staff;
use App\Models\StockCount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Closing reconciliation dashboard (Module 3d). Previews the two reconciliations —
 * cup/lid balance (v_cup_balance) and cash (v_daily_cash_summary, online EXCLUDED) —
 * then closes the day via fn_close_business_day(day, actual_cash, reason, closed_by),
 * which snapshots the cash figures + cup variances and marks status=closed (and
 * self-audits). All money/variance is read from the views; nothing is computed here.
 */
#[Layout('components.layouts.touch')]
class CloseDay extends Component
{
    use ResolvesBusinessDay;

    public ?string $actualCash = null;

    public ?string $discrepancyReason = null;

    public ?string $closedBy = null;

    /** Set after a successful close so we can show the snapshot result. */
    public ?string $closedDayId = null;

    /**
     * @return Collection<int, Staff>
     */
    #[Computed]
    public function staff(): Collection
    {
        return Staff::query()->where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function cashSummary(): ?object
    {
        if (! $this->businessDay) {
            return null;
        }

        return DB::table('v_daily_cash_summary')
            ->where('business_day_id', $this->businessDay->id)
            ->first();
    }

    /**
     * @return Collection<int, object>
     */
    #[Computed]
    public function cupBalance(): Collection
    {
        if (! $this->businessDay) {
            return collect();
        }

        return collect(DB::table('v_cup_balance')
            ->where('business_day_id', $this->businessDay->id)
            ->orderBy('item_name')
            ->get());
    }

    #[Computed]
    public function hasClosingCount(): bool
    {
        if (! $this->businessDay) {
            return false;
        }

        return StockCount::query()
            ->where('business_day_id', $this->businessDay->id)
            ->where('phase', CountPhase::Closing)
            ->exists();
    }

    #[Computed]
    public function expectedCash(): float
    {
        return (float) ($this->cashSummary->expected_cash ?? 0);
    }

    #[Computed]
    public function discrepancy(): ?float
    {
        if ($this->actualCash === null || $this->actualCash === '') {
            return null;
        }

        return (float) $this->actualCash - $this->expectedCash;
    }

    #[Computed]
    public function closedDay(): ?BusinessDay
    {
        return $this->closedDayId ? BusinessDay::find($this->closedDayId) : null;
    }

    public function close(): void
    {
        $day = $this->businessDay;
        if (! $day) {
            return;
        }

        $this->validate([
            'actualCash' => 'required|numeric|gte:0',
            'discrepancyReason' => 'nullable|string',
        ]);

        // fn_close_business_day does the snapshot + status flip + audit atomically.
        DB::statement('select fn_close_business_day(?, ?, ?, ?)', [
            $day->id,
            $this->actualCash,
            $this->discrepancyReason ?: null,
            $this->closedBy ?: null,
        ]);

        $this->closedDayId = $day->id;
        unset($this->businessDay, $this->cashSummary, $this->cupBalance);
        session()->flash('status', 'Business day closed.');
    }

    public function render()
    {
        return view('livewire.pos.close-day');
    }
}

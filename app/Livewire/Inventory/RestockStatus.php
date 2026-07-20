<?php

namespace App\Livewire\Inventory;

use App\Enums\CountPhase;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\StockCount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Read-only restock board driven by v_inventory_status (counts joined to the
 * day's par levels). Shows the latest available phase for the open day and
 * orders items by restock urgency. The computed_status is DB-owned — this
 * screen only displays it.
 */
#[Layout('components.layouts.touch')]
class RestockStatus extends Component
{
    use ResolvesBusinessDay;

    /** Severity order for the restock list (urgent first). */
    private const STATUS_ORDER = "CASE computed_status
        WHEN 'urgent' THEN 0 WHEN 'low' THEN 1 WHEN 'below_par' THEN 2 WHEN 'enough' THEN 3 ELSE 4 END";

    /**
     * Prefer the closing sheet once it exists; otherwise the opening sheet.
     */
    #[Computed]
    public function phase(): ?CountPhase
    {
        if (! $this->businessDay) {
            return null;
        }

        $hasClosing = StockCount::query()
            ->where('business_day_id', $this->businessDay->id)
            ->where('phase', CountPhase::Closing)
            ->exists();

        return $hasClosing ? CountPhase::Closing : CountPhase::Opening;
    }

    /**
     * @return Collection<int, object>
     */
    #[Computed]
    public function rows(): Collection
    {
        if (! $this->businessDay || ! $this->phase) {
            return collect();
        }

        return DB::table('v_inventory_status')
            ->where('business_day_id', $this->businessDay->id)
            ->where('phase', $this->phase->value)
            ->orderByRaw(self::STATUS_ORDER)
            ->orderBy('item_name')
            ->get();
    }

    public function render()
    {
        return view('livewire.inventory.restock-status');
    }
}

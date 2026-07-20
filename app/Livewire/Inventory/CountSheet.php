<?php

namespace App\Livewire\Inventory;

use App\Enums\CountPhase;
use App\Enums\StockCountMethod;
use App\Enums\StockLevel;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\Staff;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The opening/closing count sheet. Opening shows the short sheet (is_critical
 * items only); closing shows every active item. Each item is counted by the
 * field its count_method demands (quantity input vs level buttons) — the same
 * contract trg_stock_count_line_biu enforces. A day allows one count per phase;
 * once submitted the sheet is read-only (records are append-only).
 */
#[Layout('components.layouts.touch')]
class CountSheet extends Component
{
    use ResolvesBusinessDay;

    /** Raw phase value ('opening'|'closing'); the enum is exposed via phaseEnum(). */
    public string $phase;

    /** @var array<string, array{qty: string|null, level: string|null}> keyed by stock_item_id */
    public array $counts = [];

    public ?string $submittedBy = null;

    public ?string $shiftLead = null;

    public ?string $productionSupport = null;

    public ?string $backupStaff = null;

    public function mount(string $phase): void
    {
        $this->phase = CountPhase::from($phase)->value;

        foreach ($this->items as $item) {
            $this->counts[$item->id] = ['qty' => null, 'level' => null];
        }
    }

    #[Computed]
    public function phaseEnum(): CountPhase
    {
        return CountPhase::from($this->phase);
    }

    /**
     * Items in scope for this phase: the short critical sheet at opening, the
     * full active list at close. Critical items float to the top either way.
     *
     * @return Collection<int, StockItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return StockItem::query()
            ->where('is_active', true)
            ->when($this->phaseEnum === CountPhase::Opening, fn ($q) => $q->where('is_critical', true))
            ->orderByDesc('is_critical')
            ->orderBy('name')
            ->get();
    }

    /**
     * The already-submitted count for this day+phase, if any (sheet is then read-only).
     */
    #[Computed]
    public function existingCount(): ?StockCount
    {
        if (! $this->businessDay) {
            return null;
        }

        return StockCount::query()
            ->where('business_day_id', $this->businessDay->id)
            ->where('phase', $this->phaseEnum)
            ->with('lines')
            ->first();
    }

    /**
     * @return Collection<int, Staff>
     */
    #[Computed]
    public function staff(): Collection
    {
        return Staff::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return array<int, StockLevel>
     */
    public function levels(): array
    {
        return StockLevel::cases();
    }

    public function submit(): void
    {
        if (! $this->businessDay || $this->existingCount) {
            return;
        }

        $items = $this->items;

        $this->validate(
            ['submittedBy' => ['required', 'string']],
            [],
            ['submittedBy' => 'submitted by'],
        );

        // Every listed item must be counted by the field its method demands.
        foreach ($items as $item) {
            $entry = $this->counts[$item->id] ?? ['qty' => null, 'level' => null];
            if ($item->count_method === StockCountMethod::Quantity) {
                if ($entry['qty'] === null || $entry['qty'] === '') {
                    $this->addError("counts.{$item->id}.qty", "Count required for {$item->name}.");
                }
            } elseif ($entry['level'] === null) {
                $this->addError("counts.{$item->id}.level", "Level required for {$item->name}.");
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            DB::transaction(function () use ($items): void {
                $count = StockCount::create([
                    'business_day_id' => $this->businessDay->id,
                    'phase' => $this->phaseEnum,
                    'shift_lead_id' => $this->shiftLead,
                    'production_support_id' => $this->productionSupport,
                    'backup_staff_id' => $this->backupStaff,
                    'submitted_by_id' => $this->submittedBy,
                ]);

                foreach ($items as $item) {
                    $entry = $this->counts[$item->id];
                    StockCountLine::create([
                        'stock_count_id' => $count->id,
                        'stock_item_id' => $item->id,
                        'counted_qty' => $item->count_method === StockCountMethod::Quantity ? $entry['qty'] : null,
                        'counted_level' => $item->count_method === StockCountMethod::Level ? $entry['level'] : null,
                    ]);
                }
            });
        } catch (QueryException) {
            // Unique (business_day_id, phase) — someone else submitted this phase.
            unset($this->existingCount);
            session()->flash('error', 'This count was already submitted for today.');

            return;
        }

        unset($this->existingCount);
        session()->flash('status', ucfirst($this->phase).' count submitted.');
    }

    public function render()
    {
        return view('livewire.inventory.count-sheet');
    }
}

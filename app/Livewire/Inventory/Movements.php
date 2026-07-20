<?php

namespace App\Livewire\Inventory;

use App\Enums\StockMovementType;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Record deliveries and wastage against the open day. These adjust the cup/lid
 * balance between the opening and closing counts (v_cup_balance). Append-only:
 * each entry is a new immutable row.
 */
#[Layout('components.layouts.touch')]
class Movements extends Component
{
    use ResolvesBusinessDay;

    #[Validate('required|string')]
    public ?string $stockItemId = null;

    #[Validate('required')]
    public StockMovementType $type = StockMovementType::Delivery;

    #[Validate('required|numeric|gt:0')]
    public ?string $quantity = null;

    #[Validate('nullable|string')]
    public ?string $reason = null;

    #[Validate('nullable|string')]
    public ?string $recordedBy = null;

    /**
     * @return Collection<int, StockItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return StockItem::query()->where('is_active', true)->orderBy('name')->get();
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
     * @return Collection<int, StockMovement>
     */
    #[Computed]
    public function movements(): Collection
    {
        if (! $this->businessDay) {
            return collect();
        }

        return StockMovement::query()
            ->where('business_day_id', $this->businessDay->id)
            ->with(['stockItem', 'createdBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function record(): void
    {
        if (! $this->businessDay) {
            return;
        }

        $this->validate();

        StockMovement::create([
            'business_day_id' => $this->businessDay->id,
            'stock_item_id' => $this->stockItemId,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'created_by' => $this->recordedBy,
        ]);

        $this->reset(['stockItemId', 'quantity', 'reason']);
        unset($this->movements);
        session()->flash('status', 'Movement recorded.');
    }

    public function render()
    {
        return view('livewire.inventory.movements');
    }
}

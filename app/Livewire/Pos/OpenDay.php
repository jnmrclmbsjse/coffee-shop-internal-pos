<?php

namespace App\Livewire\Pos;

use App\Enums\DayType;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\BusinessDay;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Open the business_day everything else anchors on (Module 3a). One day per
 * calendar date (business_date is UNIQUE); only one day open at a time. When a
 * day is already open this screen shows its summary instead of the form — closing
 * a day is sub-phase 3d. Reuses the shared touch shell + ResolvesBusinessDay.
 */
#[Layout('components.layouts.touch')]
class OpenDay extends Component
{
    use ResolvesBusinessDay;

    #[Validate('required|date')]
    public string $businessDate;

    #[Validate('required')]
    public DayType $dayType = DayType::Normal;

    #[Validate('required|numeric|gte:0')]
    public string $cashFloat = '0';

    #[Validate('nullable|string')]
    public ?string $openedBy = null;

    public function mount(): void
    {
        $this->businessDate = now()->toDateString();
    }

    /**
     * @return Collection<int, Staff>
     */
    #[Computed]
    public function staff(): Collection
    {
        return Staff::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function open(): void
    {
        // A day is already open — nothing to do here; direct staff to that day.
        if ($this->businessDay) {
            return;
        }

        $this->validate();

        // business_date is UNIQUE: a closed day may already exist for this date.
        if (BusinessDay::query()->where('business_date', $this->businessDate)->exists()) {
            $this->addError('businessDate', 'A business day already exists for this date.');

            return;
        }

        BusinessDay::create([
            'business_date' => $this->businessDate,
            'day_type' => $this->dayType,
            'cash_float' => $this->cashFloat,
            'opened_by' => $this->openedBy,
        ]);

        unset($this->businessDay);
        session()->flash('status', 'Business day opened.');
    }

    public function render()
    {
        return view('livewire.pos.open-day');
    }
}

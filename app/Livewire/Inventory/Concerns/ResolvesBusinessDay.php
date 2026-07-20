<?php

namespace App\Livewire\Inventory\Concerns;

use App\Enums\BusinessDayStatus;
use App\Models\BusinessDay;
use Livewire\Attributes\Computed;

/**
 * The inventory screens all act on the day currently in session. Opening a day
 * is Module 3's job (deferred); until then the latest open business_day is the
 * working day, and screens show an empty state when none is open.
 */
trait ResolvesBusinessDay
{
    #[Computed]
    public function businessDay(): ?BusinessDay
    {
        return BusinessDay::query()
            ->where('status', BusinessDayStatus::Open)
            ->orderByDesc('business_date')
            ->first();
    }
}

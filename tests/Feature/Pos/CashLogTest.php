<?php

namespace Tests\Feature\Pos;

use App\Livewire\Pos\CashLog;
use App\Models\BusinessDay;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_records_a_cash_in_movement(): void
    {
        $day = BusinessDay::factory()->create();
        $staff = Staff::factory()->create();

        Livewire::test(CashLog::class)
            ->set('kind', 'cash_in')
            ->set('amount', '500')
            ->set('reason', 'bank change')
            ->set('recordedBy', $staff->id)
            ->call('record')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_movement', [
            'business_day_id' => $day->id,
            'type' => 'cash_in',
            'amount' => 500,
            'reason' => 'bank change',
            'created_by' => $staff->id,
        ]);
    }

    public function test_it_records_a_cash_out_movement(): void
    {
        $day = BusinessDay::factory()->create();

        Livewire::test(CashLog::class)
            ->set('kind', 'cash_out')
            ->set('amount', '120')
            ->set('reason', 'petty cash')
            ->call('record')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_movement', [
            'business_day_id' => $day->id,
            'type' => 'cash_out',
            'amount' => 120,
        ]);
    }

    public function test_it_records_an_expense_with_category(): void
    {
        $day = BusinessDay::factory()->create();

        Livewire::test(CashLog::class)
            ->set('kind', 'expense')
            ->set('amount', '250')
            ->set('category', 'supplies')
            ->set('reason', 'napkins')
            ->call('record')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('expense', [
            'business_day_id' => $day->id,
            'amount' => 250,
            'category' => 'supplies',
            'reason' => 'napkins',
        ]);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_it_validates_amount_and_reason(): void
    {
        BusinessDay::factory()->create();

        Livewire::test(CashLog::class)
            ->set('kind', 'cash_in')
            ->set('amount', '0')
            ->set('reason', '')
            ->call('record')
            ->assertHasErrors(['amount', 'reason']);
    }

    public function test_the_log_merges_movements_and_expenses_for_the_day(): void
    {
        $day = BusinessDay::factory()->create();
        CashMovement::factory()->for($day, 'businessDay')->create(['reason' => 'opening change']);
        Expense::factory()->for($day, 'businessDay')->create(['reason' => 'coffee beans']);

        Livewire::test(CashLog::class)
            ->assertSee('opening change')
            ->assertSee('coffee beans')
            ->assertSee('Expense');
    }

    public function test_it_shows_empty_state_without_an_open_day(): void
    {
        Livewire::test(CashLog::class)
            ->assertSee('No business day is open');
    }

    public function test_guests_are_redirected_from_the_route(): void
    {
        auth()->logout();

        $this->get(route('pos.cash'))->assertRedirect();
    }
}

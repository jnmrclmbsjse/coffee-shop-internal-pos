<?php

namespace Tests\Feature\Pos;

use App\Enums\BusinessDayStatus;
use App\Enums\DayType;
use App\Livewire\Pos\OpenDay;
use App\Models\BusinessDay;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpenDayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_it_opens_a_business_day(): void
    {
        $staff = Staff::factory()->create();

        Livewire::test(OpenDay::class)
            ->set('businessDate', '2026-07-21')
            ->set('dayType', DayType::Peak->value)
            ->set('cashFloat', '1500')
            ->set('openedBy', $staff->id)
            ->call('open')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('business_day', [
            'business_date' => '2026-07-21',
            'day_type' => DayType::Peak->value,
            'status' => BusinessDayStatus::Open->value,
            'cash_float' => 1500,
            'opened_by' => $staff->id,
        ]);
    }

    public function test_it_shows_the_open_day_instead_of_the_form(): void
    {
        BusinessDay::factory()->create(['business_date' => '2026-07-21']);

        Livewire::test(OpenDay::class)
            ->assertSee('Day open')
            ->assertDontSee('Open day');
    }

    public function test_it_does_not_open_a_second_day_while_one_is_open(): void
    {
        BusinessDay::factory()->create();

        Livewire::test(OpenDay::class)
            ->set('businessDate', '2026-07-21')
            ->call('open');

        // Only the pre-existing open day remains; no second row created.
        $this->assertSame(1, BusinessDay::count());
    }

    public function test_it_rejects_a_duplicate_business_date(): void
    {
        // A prior (closed) day already exists for this date.
        BusinessDay::factory()->closed()->create(['business_date' => '2026-07-21']);

        Livewire::test(OpenDay::class)
            ->set('businessDate', '2026-07-21')
            ->set('cashFloat', '0')
            ->call('open')
            ->assertHasErrors('businessDate');

        $this->assertSame(1, BusinessDay::count());
    }

    public function test_it_validates_required_fields(): void
    {
        Livewire::test(OpenDay::class)
            ->set('businessDate', '')
            ->set('cashFloat', '')
            ->call('open')
            ->assertHasErrors(['businessDate', 'cashFloat']);
    }

    public function test_guests_are_redirected_from_the_route(): void
    {
        auth()->logout();

        $this->get(route('pos.open'))->assertRedirect();
    }
}

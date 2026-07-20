<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_lists_staff(): void
    {
        $staff = Staff::factory()->count(3)->create();

        Livewire::test(ManageStaff::class)
            ->assertCanSeeTableRecords($staff);
    }

    public function test_it_creates_a_staff_member(): void
    {
        Livewire::test(ManageStaff::class)
            ->callAction('create', [
                'name' => 'Barista Ana',
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('staff', ['name' => 'Barista Ana']);
    }

    public function test_it_requires_a_name(): void
    {
        Livewire::test(ManageStaff::class)
            ->callAction('create', [
                'name' => null,
            ])
            ->assertHasActionErrors(['name' => 'required']);
    }
}

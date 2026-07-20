<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StockCategories\Pages\ManageStockCategories;
use App\Models\StockCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_creates_a_stock_category(): void
    {
        Livewire::test(ManageStockCategories::class)
            ->callAction('create', [
                'name' => 'Cups',
                'sort_weight' => 1,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_category', [
            'name' => 'Cups',
            'sort_weight' => 1,
        ]);
    }

    public function test_it_rejects_a_duplicate_name(): void
    {
        StockCategory::factory()->create(['name' => 'Cups']);

        Livewire::test(ManageStockCategories::class)
            ->callAction('create', [
                'name' => 'Cups',
                'sort_weight' => 0,
            ])
            ->assertHasActionErrors(['name']);
    }
}

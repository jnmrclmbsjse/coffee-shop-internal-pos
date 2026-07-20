<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_creates_a_product_category(): void
    {
        Livewire::test(ManageProductCategories::class)
            ->callAction('create', [
                'name' => 'Espresso',
                'sort_weight' => 5,
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('product_category', [
            'name' => 'Espresso',
            'sort_weight' => 5,
        ]);
    }

    public function test_it_rejects_a_duplicate_name(): void
    {
        ProductCategory::factory()->create(['name' => 'Espresso']);

        Livewire::test(ManageProductCategories::class)
            ->callAction('create', [
                'name' => 'Espresso',
                'sort_weight' => 0,
            ])
            ->assertHasActionErrors(['name']);
    }
}

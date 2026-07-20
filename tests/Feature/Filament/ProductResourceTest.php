<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_it_creates_a_product_with_a_size_mapped_to_a_cup_and_lid(): void
    {
        $category = ProductCategory::factory()->create();
        $cup = StockItem::factory()->cup()->create();
        $lid = StockItem::factory()->lid()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => $category->id,
                'name' => 'Cafe Latte',
                'sizes' => [
                    [
                        'label' => 'M',
                        'price' => 150,
                        'cup_stock_item_id' => $cup->id,
                        'lid_stock_item_id' => $lid->id,
                        'sort_weight' => 0,
                        'is_active' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Cafe Latte')->sole();
        $size = $product->sizes->sole();

        $this->assertSame('150.00', $size->price);
        $this->assertSame($cup->id, $size->cup_stock_item_id);
        $this->assertSame($lid->id, $size->lid_stock_item_id);
    }

    public function test_it_requires_a_category_and_name(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'category_id' => null,
                'name' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'category_id' => 'required',
                'name' => 'required',
            ]);
    }
}

<?php

namespace Tests\Feature\Models;

use App\Enums\StockCountMethod;
use App\Models\ParLevel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSize;
use App\Models\Staff;
use App\Models\StockCategory;
use App\Models\StockItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_factories_persist_with_uuid_keys(): void
    {
        $staff = Staff::factory()->create();
        $size = ProductSize::factory()->withCupAndLid()->create();

        $this->assertIsString($staff->getKey());
        $this->assertTrue(Staff::whereKey($staff->getKey())->exists());
        $this->assertTrue(ProductSize::whereKey($size->getKey())->exists());
    }

    public function test_relationships_resolve_across_the_catalog(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->for($category, 'category')->create();
        $size = ProductSize::factory()->withCupAndLid()->for($product, 'product')->create();

        $this->assertTrue($product->category->is($category));
        $this->assertTrue($category->products->contains($product));
        $this->assertTrue($product->sizes->contains($size));
        $this->assertTrue($size->cupStockItem->is_reconciled);
        $this->assertTrue($size->lidStockItem->is_reconciled);
    }

    public function test_par_level_belongs_to_a_stock_item_and_has_no_timestamps(): void
    {
        $par = ParLevel::factory()->create();

        $this->assertFalse($par->timestamps);
        $this->assertInstanceOf(StockItem::class, $par->stockItem);
    }

    public function test_reconciled_items_must_count_by_quantity(): void
    {
        // chk_reconciled_is_quantity — a reconciled level item is rejected by the DB.
        $this->expectException(QueryException::class);

        StockItem::factory()->create([
            'is_reconciled' => true,
            'count_method' => StockCountMethod::Level,
        ]);
    }

    public function test_par_level_requires_at_least_one_target(): void
    {
        // chk_par_has_target — neither the quantity nor the level set is populated.
        $this->expectException(QueryException::class);

        ParLevel::factory()->create([
            'par_qty' => null,
            'low_qty_threshold' => null,
            'urgent_qty_threshold' => null,
            'par_level_value' => null,
            'low_level_threshold' => null,
            'urgent_level_threshold' => null,
        ]);
    }

    public function test_reconciled_in_category_scope_only_returns_matching_reconciled_items(): void
    {
        $cup = StockItem::factory()->cup()->create();
        $lid = StockItem::factory()->lid()->create();

        // A reconciled item in a non-cup/non-lid category must be excluded.
        $dairy = StockCategory::factory()->create(['name' => 'Dairy']);
        StockItem::factory()->for($dairy, 'category')->create(['is_reconciled' => true]);

        // A cup-category item that is NOT reconciled must be excluded.
        $paperCups = StockCategory::factory()->create(['name' => 'Paper Cups']);
        StockItem::factory()->for($paperCups, 'category')->create(['is_reconciled' => false]);

        $cups = StockItem::query()->reconciledInCategory('cup')->pluck('id');
        $lids = StockItem::query()->reconciledInCategory('lid')->pluck('id');

        $this->assertEquals([$cup->id], $cups->all());
        $this->assertEquals([$lid->id], $lids->all());
    }
}

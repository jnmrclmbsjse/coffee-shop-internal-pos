<?php

namespace App\Models;

use App\Enums\StockCountMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A consumable tracked by the shop. Only cups & lids are `is_reconciled` (their
 * balance is proven against sales); everything else is count-only. `is_critical`
 * items appear on the short opening sheet. Reconciled items must count by
 * quantity (chk_reconciled_is_quantity).
 */
#[Fillable(['category_id', 'name', 'unit', 'size', 'count_method', 'is_reconciled', 'is_critical', 'is_active'])]
class StockItem extends PosModel
{
    protected $table = 'stock_item';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count_method' => StockCountMethod::class,
            'is_reconciled' => 'boolean',
            'is_critical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StockCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'category_id');
    }

    /**
     * @return HasMany<ParLevel, $this>
     */
    public function parLevels(): HasMany
    {
        return $this->hasMany(ParLevel::class, 'stock_item_id');
    }

    /**
     * Active, reconciled items whose category name contains the given keyword
     * ("cup" / "lid"). The category name is the only thing distinguishing a cup
     * stock item from a lid one — see EXISTING-PATTERNS §6. Used to filter the
     * cup/lid selects on a product size (and reusable by the POS drawdown).
     *
     * @param  Builder<StockItem>  $query
     */
    public function scopeReconciledInCategory(Builder $query, string $keyword): void
    {
        $query->where('is_reconciled', true)
            ->where('is_active', true)
            ->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->whereRaw(
                'lower(name) like ?',
                ['%'.strtolower($keyword).'%'],
            ));
    }
}

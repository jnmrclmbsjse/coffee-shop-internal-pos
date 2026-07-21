<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer order within a business day. Starts `parked`, becomes `completed`
 * (which must declare payment_method — chk_completed_has_payment) or `void`.
 * Corrections are void + re-enter, never edits. order_number is a per-day
 * sequence (UNIQUE per business_day). The money rollups (subtotal, discount_amount,
 * total) are maintained by trg_recalc_order_totals from the line items — they are
 * DB-authoritative and kept out of #[Fillable] (see EXISTING-PATTERNS §6).
 */
#[Fillable([
    'business_day_id', 'order_number', 'customer_name', 'service_type',
    'payment_method', 'status', 'created_by', 'completed_at', 'voided_at', 'void_reason',
])]
class SalesOrder extends PosModel
{
    protected $table = 'sales_order';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BusinessDay, $this>
     */
    public function businessDay(): BelongsTo
    {
        return $this->belongsTo(BusinessDay::class, 'business_day_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * @return HasMany<SalesOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }
}

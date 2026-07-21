<?php

namespace App\Livewire\Pos;

use App\Enums\AuditAction;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Livewire\Inventory\Concerns\ResolvesBusinessDay;
use App\Models\BusinessDay;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSize;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Touch-first order taking (Module 3b). Orders persist per line: the moment the
 * first item is added the sales_order is created `parked`, and each line is a real
 * sales_order_item so trg_recalc_order_totals keeps the rollups correct (no PHP
 * total math — see EXISTING-PATTERNS §6). Park just releases the screen (the order
 * stays parked and resumable); completion sets payment_method (chk_completed_has_payment);
 * corrections are void + re-enter, and a void writes an append-only audit_log row.
 */
#[Layout('components.layouts.touch')]
class OrderTaking extends Component
{
    use ResolvesBusinessDay;

    /** The parked order currently being built/resumed; null until the first line. */
    public ?string $orderId = null;

    public ?string $activeCategoryId = null;

    public string $customerName = '';

    public bool $confirmingVoid = false;

    public string $voidReason = '';

    public function mount(): void
    {
        $this->activeCategoryId = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_weight')
            ->value('id');
    }

    /**
     * @return Collection<int, ProductCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_weight')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        if (! $this->activeCategoryId) {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->where('category_id', $this->activeCategoryId)
            ->with(['sizes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_weight')])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function order(): ?SalesOrder
    {
        if (! $this->orderId) {
            return null;
        }

        return SalesOrder::query()
            ->with(['items' => fn ($q) => $q->with('productSize.product')->orderBy('id')])
            ->find($this->orderId);
    }

    /**
     * @return Collection<int, SalesOrder>
     */
    #[Computed]
    public function parkedOrders(): Collection
    {
        if (! $this->businessDay) {
            return collect();
        }

        return SalesOrder::query()
            ->where('business_day_id', $this->businessDay->id)
            ->where('status', OrderStatus::Parked)
            ->withCount('items')
            ->orderBy('order_number')
            ->get();
    }

    public function selectCategory(string $categoryId): void
    {
        $this->activeCategoryId = $categoryId;
        unset($this->products);
    }

    public function addItem(string $productSizeId): void
    {
        if (! $this->businessDay) {
            return;
        }

        $size = ProductSize::find($productSizeId);
        if (! $size) {
            return;
        }

        $order = $this->ensureOrder();

        // Merge repeat taps of the same undiscounted size into one line.
        $existing = $order->items()
            ->where('product_size_id', $size->id)
            ->where('discount_type', DiscountType::None)
            ->first();

        if ($existing) {
            $existing->increment('quantity');
        } else {
            $order->items()->create([
                'product_size_id' => $size->id,
                'quantity' => 1,
                'unit_price' => $size->price,
                'discount_type' => DiscountType::None,
            ]);
        }

        unset($this->order, $this->parkedOrders);
    }

    public function incrementQty(string $itemId): void
    {
        $this->lineFor($itemId)?->increment('quantity');
        unset($this->order);
    }

    public function decrementQty(string $itemId): void
    {
        $item = $this->lineFor($itemId);
        if (! $item) {
            return;
        }

        if ($item->quantity <= 1) {
            $item->delete();
        } else {
            $item->decrement('quantity');
        }

        unset($this->order, $this->parkedOrders);
    }

    public function removeItem(string $itemId): void
    {
        $this->lineFor($itemId)?->delete();
        unset($this->order, $this->parkedOrders);
    }

    public function setDiscount(string $itemId, string $discountType): void
    {
        $item = $this->lineFor($itemId);
        if (! $item) {
            return;
        }

        $item->update(['discount_type' => DiscountType::from($discountType)]);
        unset($this->order);
    }

    public function saveTaste(string $itemId, ?string $value): void
    {
        $this->lineFor($itemId)?->update(['taste_preference' => $value ?: null]);
        unset($this->order);
    }

    public function setService(string $serviceType): void
    {
        $this->order?->update(['service_type' => ServiceType::from($serviceType)]);
        unset($this->order);
    }

    public function updatedCustomerName(string $value): void
    {
        $this->order?->update(['customer_name' => $value ?: null]);
        unset($this->order);
    }

    public function resumeOrder(string $orderId): void
    {
        $order = SalesOrder::query()
            ->where('status', OrderStatus::Parked)
            ->find($orderId);

        if (! $order) {
            return;
        }

        $this->orderId = $order->id;
        $this->customerName = (string) $order->customer_name;
        unset($this->order);
    }

    public function park(): void
    {
        // The order is already persisted as parked; discard an empty one so we
        // don't leave junk rows behind.
        $order = $this->order;
        if ($order && $order->items->isEmpty()) {
            $order->delete();
        }

        $this->clearActiveOrder();
        session()->flash('status', 'Order parked.');
    }

    public function completeOrder(string $paymentMethod): void
    {
        $order = $this->order;
        if (! $order || $order->items->isEmpty()) {
            $this->addError('order', 'Add at least one item before completing.');

            return;
        }

        $order->update([
            'status' => OrderStatus::Completed,
            'payment_method' => PaymentMethod::from($paymentMethod),
            'completed_at' => now(),
        ]);

        $number = $order->order_number;
        $this->clearActiveOrder();
        session()->flash('status', "Order #{$number} completed.");
    }

    public function voidOrder(): void
    {
        $this->validate(['voidReason' => 'required|string|min:3']);

        $order = $this->order;
        if (! $order) {
            return;
        }

        DB::transaction(function () use ($order) {
            $before = $order->attributesToArray();

            $order->update([
                'status' => OrderStatus::Void,
                'voided_at' => now(),
                'void_reason' => $this->voidReason,
            ]);

            // Append-only audit trail for the correction path (id + changed_at
            // default in Postgres). Mirrors the seed's void audit row.
            DB::table('audit_log')->insert([
                'entity_type' => 'sales_order',
                'entity_id' => $order->id,
                'action' => AuditAction::Void->value,
                'before' => json_encode($before),
                'after' => json_encode($order->fresh()->attributesToArray()),
                'note' => $this->voidReason,
            ]);
        });

        $number = $order->order_number;
        $this->clearActiveOrder();
        session()->flash('status', "Order #{$number} voided.");
    }

    /**
     * Resolve a line that actually belongs to the active order (integrity guard).
     */
    protected function lineFor(string $itemId): ?SalesOrderItem
    {
        if (! $this->orderId) {
            return null;
        }

        return SalesOrderItem::query()
            ->where('sales_order_id', $this->orderId)
            ->find($itemId);
    }

    protected function ensureOrder(): SalesOrder
    {
        if ($this->order) {
            return $this->order;
        }

        $order = DB::transaction(function () {
            // Serialize per-day numbering by locking the day row (Postgres won't
            // take FOR UPDATE on an aggregate). The UNIQUE (day, number) is the backstop.
            BusinessDay::whereKey($this->businessDay->id)->lockForUpdate()->first();

            $next = (int) SalesOrder::query()
                ->where('business_day_id', $this->businessDay->id)
                ->max('order_number') + 1;

            return SalesOrder::create([
                'business_day_id' => $this->businessDay->id,
                'order_number' => $next,
                'customer_name' => $this->customerName ?: null,
                'service_type' => ServiceType::TakeOut,
                'status' => OrderStatus::Parked,
            ]);
        });

        $this->orderId = $order->id;
        unset($this->order);

        return $order;
    }

    protected function clearActiveOrder(): void
    {
        $this->reset(['orderId', 'customerName', 'confirmingVoid', 'voidReason']);
        unset($this->order, $this->parkedOrders);
    }

    public function render()
    {
        return view('livewire.pos.order-taking');
    }
}

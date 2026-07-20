<?php

namespace Tests\Unit;

use App\Enums\AuditAction;
use App\Enums\BusinessDayStatus;
use App\Enums\CashMovementType;
use App\Enums\CountPhase;
use App\Enums\DayType;
use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\ParStatus;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Enums\StockCountMethod;
use App\Enums\StockLevel;
use App\Enums\StockMovementType;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;

/**
 * Locks the backed-enum values to the Postgres DDL character-for-character.
 * A mismatch would surface only as a runtime cast failure against real data.
 */
class EnumValuesTest extends TestCase
{
    public function test_enum_values_match_the_postgres_ddl(): void
    {
        $this->assertSame(['normal', 'peak'], array_column(DayType::cases(), 'value'));
        $this->assertSame(['open', 'closed'], array_column(BusinessDayStatus::cases(), 'value'));
        $this->assertSame(['dine_in', 'take_out'], array_column(ServiceType::cases(), 'value'));
        $this->assertSame(['cash', 'online'], array_column(PaymentMethod::cases(), 'value'));
        $this->assertSame(['none', 'pwd', 'senior'], array_column(DiscountType::cases(), 'value'));
        $this->assertSame(['parked', 'completed', 'void'], array_column(OrderStatus::cases(), 'value'));
        $this->assertSame(['quantity', 'level'], array_column(StockCountMethod::cases(), 'value'));
        $this->assertSame(
            ['empty', 'low', 'quarter', 'third', 'half', 'two_thirds', 'three_quarters', 'full'],
            array_column(StockLevel::cases(), 'value'),
        );
        $this->assertSame(['opening', 'closing'], array_column(CountPhase::cases(), 'value'));
        $this->assertSame(['urgent', 'low', 'below_par', 'enough'], array_column(ParStatus::cases(), 'value'));
        $this->assertSame(['delivery', 'wastage'], array_column(StockMovementType::cases(), 'value'));
        $this->assertSame(['cash_in', 'cash_out'], array_column(CashMovementType::cases(), 'value'));
        $this->assertSame(['create', 'update', 'void', 'close', 'reopen'], array_column(AuditAction::cases(), 'value'));
    }

    public function test_every_domain_enum_exposes_a_filament_label(): void
    {
        $this->assertInstanceOf(HasLabel::class, OrderStatus::Completed);
        $this->assertSame('PWD', DiscountType::Pwd->getLabel());
        $this->assertSame('Dine-in', ServiceType::DineIn->getLabel());
    }
}

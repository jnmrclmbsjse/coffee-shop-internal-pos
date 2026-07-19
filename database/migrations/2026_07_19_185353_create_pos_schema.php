<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Loads the verified POS schema (enums, tables, generated columns, triggers,
 * views, and fn_close_business_day) from docs/schema/schema.sql — the single
 * source of truth. See docs/schema/erd.md for the data dictionary.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(base_path('docs/schema/schema.sql')));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP VIEW IF EXISTS v_inventory_status, v_daily_cash_summary, v_cup_balance;
            DROP FUNCTION IF EXISTS fn_close_business_day(uuid, numeric, text, uuid);
            DROP FUNCTION IF EXISTS trg_recalc_order_totals() CASCADE;
            DROP FUNCTION IF EXISTS trg_stock_count_line_biu() CASCADE;
            DROP FUNCTION IF EXISTS fn_par_status(uuid, day_type, numeric, stock_level);
            DROP TABLE IF EXISTS audit_log, stock_count_line, stock_count,
                stock_movement, expense, cash_movement, sales_order_item, sales_order,
                business_day, par_level, product_size, stock_item, stock_category,
                product, product_category, staff CASCADE;
            DROP TYPE IF EXISTS audit_action, cash_movement_type, stock_movement_type,
                par_status, count_phase, stock_level, stock_count_method, order_status,
                discount_type, payment_method, service_type, business_day_status, day_type;
        SQL);
    }
};

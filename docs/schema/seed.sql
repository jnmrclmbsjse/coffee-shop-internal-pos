-- =====================================================================
-- Coffee Shop POS — seed + worked reconciliation examples
-- Run AFTER schema.sql on the same database.
--
-- One business day (2026-07-20, normal) that exercises both reconciliations:
--   * Cup balance:  open 100 medium cups, deliver 50, sell 120, waste 3
--                   -> expected 27, count 25 -> variance -2
--   * Cash:         float 2,000; cash 5,000; online 3,000; cash_in 100;
--                   cash_out 200; expenses 500 -> expected_cash 6,400
--                   (NOT 8,400 — online excluded); actual 6,350 -> -50
-- Also demonstrates a PARKED order (per-line senior discount) and a
-- VOIDED order (excluded from every sum, written to audit_log).
-- =====================================================================

DO $$
DECLARE
    -- staff
    v_ana uuid; v_ben uuid; v_carmen uuid;
    -- catalog
    v_cat_coffee uuid;
    v_sc_cups uuid; v_sc_lids uuid; v_sc_dairy uuid; v_sc_other uuid;
    v_cup_m uuid; v_lid_m uuid; v_milk uuid; v_yakult uuid; v_straw uuid;
    v_house uuid; v_latte uuid;
    v_house_m uuid; v_latte_m uuid;
    -- operations
    v_day uuid;
    v_open uuid; v_close uuid;
    v_o1 uuid; v_o2 uuid; v_o3 uuid; v_o4 uuid;
BEGIN
    -- ---- staff roster ----
    INSERT INTO staff(name) VALUES ('Ana')    RETURNING id INTO v_ana;
    INSERT INTO staff(name) VALUES ('Ben')    RETURNING id INTO v_ben;
    INSERT INTO staff(name) VALUES ('Carmen') RETURNING id INTO v_carmen;

    -- ---- categories ----
    INSERT INTO product_category(name, sort_weight) VALUES ('Coffee', 1) RETURNING id INTO v_cat_coffee;
    INSERT INTO stock_category(name, sort_weight) VALUES ('Cups', 1)  RETURNING id INTO v_sc_cups;
    INSERT INTO stock_category(name, sort_weight) VALUES ('Lids', 2)  RETURNING id INTO v_sc_lids;
    INSERT INTO stock_category(name, sort_weight) VALUES ('Dairy', 3) RETURNING id INTO v_sc_dairy;
    INSERT INTO stock_category(name, sort_weight) VALUES ('Others', 4) RETURNING id INTO v_sc_other;

    -- ---- stock items ----
    -- reconciled (cups/lids) + critical (show on opening sheet)
    INSERT INTO stock_item(category_id, name, unit, size, count_method, is_reconciled, is_critical)
        VALUES (v_sc_cups, 'Medium Cold Cup', 'pcs', 'M', 'quantity', true, true)
        RETURNING id INTO v_cup_m;
    INSERT INTO stock_item(category_id, name, unit, size, count_method, is_reconciled, is_critical)
        VALUES (v_sc_lids, 'Medium Lid', 'pcs', 'M', 'quantity', true, true)
        RETURNING id INTO v_lid_m;
    -- count-only + critical
    INSERT INTO stock_item(category_id, name, unit, count_method, is_reconciled, is_critical)
        VALUES (v_sc_dairy, 'Fresh Milk', 'liter', 'level', false, true)
        RETURNING id INTO v_milk;
    INSERT INTO stock_item(category_id, name, unit, count_method, is_reconciled, is_critical)
        VALUES (v_sc_other, 'Yakult', 'bottle', 'quantity', false, true)
        RETURNING id INTO v_yakult;
    -- count-only, non-critical (closing sheet only)
    INSERT INTO stock_item(category_id, name, unit, count_method, is_reconciled, is_critical)
        VALUES (v_sc_other, 'Straw', 'pcs', 'quantity', false, false)
        RETURNING id INTO v_straw;

    -- ---- products + sizes (Medium maps to Medium cup + lid) ----
    INSERT INTO product(category_id, name) VALUES (v_cat_coffee, 'House Blend')     RETURNING id INTO v_house;
    INSERT INTO product(category_id, name) VALUES (v_cat_coffee, 'Signature Latte') RETURNING id INTO v_latte;

    INSERT INTO product_size(product_id, label, price, cup_stock_item_id, lid_stock_item_id)
        VALUES (v_house, 'M', 50, v_cup_m, v_lid_m) RETURNING id INTO v_house_m;
    INSERT INTO product_size(product_id, label, price, cup_stock_item_id, lid_stock_item_id)
        VALUES (v_latte, 'M', 150, v_cup_m, v_lid_m) RETURNING id INTO v_latte_m;

    -- ---- par levels (normal day) ----
    INSERT INTO par_level(stock_item_id, day_type, par_qty, low_qty_threshold, urgent_qty_threshold)
        VALUES (v_cup_m, 'normal', 40, 20, 10);
    INSERT INTO par_level(stock_item_id, day_type, par_qty, low_qty_threshold, urgent_qty_threshold)
        VALUES (v_lid_m, 'normal', 40, 20, 10);
    INSERT INTO par_level(stock_item_id, day_type, par_qty, low_qty_threshold, urgent_qty_threshold)
        VALUES (v_yakult, 'normal', 24, 12, 6);
    INSERT INTO par_level(stock_item_id, day_type, par_level_value, low_level_threshold, urgent_level_threshold)
        VALUES (v_milk, 'normal', 'half', 'quarter', 'low');

    -- ================= THE BUSINESS DAY =================
    INSERT INTO business_day(business_date, day_type, cash_float, opened_by)
        VALUES ('2026-07-20', 'normal', 2000, v_ana) RETURNING id INTO v_day;

    -- ---- opening inventory (critical items only) ----
    INSERT INTO stock_count(business_day_id, phase, shift_lead_id, submitted_by_id)
        VALUES (v_day, 'opening', v_ana, v_ana) RETURNING id INTO v_open;
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_open, v_cup_m, 100);
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_open, v_lid_m, 200);
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_open, v_yakult, 24);
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_level) VALUES (v_open, v_milk, 'full');

    -- ---- orders ----
    -- #1 completed, CASH: 100 x House Blend M @50 = 5,000
    INSERT INTO sales_order(business_day_id, order_number, customer_name, service_type, payment_method, status, completed_at)
        VALUES (v_day, 1, 'Walk-in 1', 'take_out', 'cash', 'completed', now()) RETURNING id INTO v_o1;
    INSERT INTO sales_order_item(sales_order_id, product_size_id, quantity, unit_price, taste_preference)
        VALUES (v_o1, v_house_m, 100, 50, 'regular sweetness');

    -- #2 completed, ONLINE: 20 x Signature Latte M @150 = 3,000
    INSERT INTO sales_order(business_day_id, order_number, customer_name, service_type, payment_method, status, completed_at)
        VALUES (v_day, 2, 'Walk-in 2', 'dine_in', 'online', 'completed', now()) RETURNING id INTO v_o2;
    INSERT INTO sales_order_item(sales_order_id, product_size_id, quantity, unit_price, taste_preference)
        VALUES (v_o2, v_latte_m, 20, 150, 'less ice');
    -- => 120 medium cups + 120 medium lids consumed by completed sales

    -- #3 PARKED (customer still deciding) with a per-line SENIOR discount
    INSERT INTO sales_order(business_day_id, order_number, customer_name, service_type, status)
        VALUES (v_day, 3, 'Lola Nena', 'dine_in', 'parked') RETURNING id INTO v_o3;
    INSERT INTO sales_order_item(sales_order_id, product_size_id, quantity, unit_price, discount_type)
        VALUES (v_o3, v_latte_m, 1, 150, 'senior');   -- gross 150, discount 30, line_total 120

    -- #4 VOIDED (excluded from all sums; audited)
    INSERT INTO sales_order(business_day_id, order_number, customer_name, payment_method, status, voided_at, void_reason)
        VALUES (v_day, 4, 'Void Test', 'cash', 'void', now(), 'wrong order') RETURNING id INTO v_o4;
    INSERT INTO sales_order_item(sales_order_id, product_size_id, quantity, unit_price)
        VALUES (v_o4, v_house_m, 5, 50);
    INSERT INTO audit_log(entity_type, entity_id, action, changed_by, note)
        VALUES ('sales_order', v_o4, 'void', v_ana, 'Voided before completion');

    -- ---- stock movements (mid-day) ----
    INSERT INTO stock_movement(business_day_id, stock_item_id, type, quantity, reason)
        VALUES (v_day, v_cup_m, 'delivery', 50, 'mid-day stock run');
    INSERT INTO stock_movement(business_day_id, stock_item_id, type, quantity, reason)
        VALUES (v_day, v_cup_m, 'wastage', 3, 'dropped / crushed cups');

    -- ---- cash movements + expense ----
    INSERT INTO cash_movement(business_day_id, type, amount, reason)
        VALUES (v_day, 'cash_in', 100, 'staff coins for change');
    INSERT INTO cash_movement(business_day_id, type, amount, reason)
        VALUES (v_day, 'cash_out', 200, 'owner withdrawal');
    INSERT INTO expense(business_day_id, amount, category, reason)
        VALUES (v_day, 500, 'supplies', 'ice delivery');

    -- ---- closing inventory (all items) ----
    INSERT INTO stock_count(business_day_id, phase, shift_lead_id, production_support_id, submitted_by_id)
        VALUES (v_day, 'closing', v_ben, v_carmen, v_ben) RETURNING id INTO v_close;
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_close, v_cup_m, 25);   -- expect 27 -> -2
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_close, v_lid_m, 80);   -- expect 80 ->  0
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_close, v_yakult, 10);  -- below par
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_qty)   VALUES (v_close, v_straw, 300);
    INSERT INTO stock_count_line(stock_count_id, stock_item_id, counted_level) VALUES (v_close, v_milk, 'quarter'); -- low

    -- ---- close the day (human supplies actual counted cash) ----
    PERFORM fn_close_business_day(v_day, 6350, 'Php 50 short, under investigation', v_ben);
END $$;

-- =====================================================================
-- VERIFICATION QUERIES (expected results in comments)
-- =====================================================================

-- Cup balance: Medium Cold Cup -> expected 27, actual 25, variance -2
--              Medium Lid      -> expected 80, actual 80, variance  0
SELECT item_name, opening_qty, delivery_qty, sold_qty, wastage_qty,
       expected_close, actual_close, variance
FROM v_cup_balance
WHERE business_date = '2026-07-20'
ORDER BY item_name;

-- Cash: cash 5000 | online 3000 | gross 8000 | expenses 500 |
--       cash_in 100 | cash_out 200 | expected_cash 6400 | net_turnover 4500
SELECT cash_sales, online_sales, gross_sales, total_expenses,
       total_cash_in, total_cash_out, expected_cash, net_cash_turnover
FROM v_daily_cash_summary
WHERE business_date = '2026-07-20';

-- Snapshot stored on the day: expected 6400, actual 6350, discrepancy -50
SELECT expected_cash, actual_cash, cash_discrepancy, net_cash_turnover, status
FROM business_day WHERE business_date = '2026-07-20';

-- Inventory status (closing) drives the restock list.
SELECT item_name, counted_qty, counted_level, par_qty, par_level_value, computed_status
FROM v_inventory_status
WHERE business_date = '2026-07-20' AND phase = 'closing'
ORDER BY item_name;

-- Parked order shows the per-line senior discount rolled up (150 -> 120).
SELECT customer_name, status, subtotal, discount_amount, total
FROM sales_order WHERE order_number = 3;

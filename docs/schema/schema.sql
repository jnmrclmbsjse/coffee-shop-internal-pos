-- =====================================================================
-- Coffee Shop POS — Phase 1 schema (PostgreSQL, ORM-agnostic reference)
-- =====================================================================
-- Modules: Product Management, POS/Sales, Inventory.
-- Everything hangs off a `business_day` session anchor.
-- Two reconciliations earn their keep:
--   1. cup/lid balance   (v_cup_balance)
--   2. cash reconciliation (v_daily_cash_summary + fn_close_business_day)
--
-- Design notes:
--   * Money is never hand-set on line items: gross/discount/line_total are
--     GENERATED columns, and order rollups are trigger-maintained. This is
--     how "the POS computes, the human only verifies" is enforced at the DB.
--   * Corrections are void + re-enter, never silent edits; audit_log is
--     append-only.
--   * Expected physical cash EXCLUDES online sales (fixes the manual bug).
-- =====================================================================

-- gen_random_uuid() is built into core Postgres (>= 13). If on an older
-- server, `CREATE EXTENSION IF NOT EXISTS pgcrypto;` provides it.

-- ------------------------------------------------------------------
-- Enumerated types
-- ------------------------------------------------------------------
CREATE TYPE day_type            AS ENUM ('normal', 'peak');
CREATE TYPE business_day_status AS ENUM ('open', 'closed');
CREATE TYPE service_type        AS ENUM ('dine_in', 'take_out');
CREATE TYPE payment_method      AS ENUM ('cash', 'online');
CREATE TYPE discount_type       AS ENUM ('none', 'pwd', 'senior');
CREATE TYPE order_status        AS ENUM ('parked', 'completed', 'void');
CREATE TYPE stock_count_method  AS ENUM ('quantity', 'level');
-- declared ascending so `<` / `>=` comparisons are meaningful
CREATE TYPE stock_level         AS ENUM ('empty','low','quarter','third','half','two_thirds','three_quarters','full');
CREATE TYPE count_phase         AS ENUM ('opening', 'closing');
CREATE TYPE par_status          AS ENUM ('urgent', 'low', 'below_par', 'enough');
CREATE TYPE stock_movement_type AS ENUM ('delivery', 'wastage');
CREATE TYPE cash_movement_type  AS ENUM ('cash_in', 'cash_out');
CREATE TYPE audit_action        AS ENUM ('create', 'update', 'void', 'close', 'reopen');

-- ==================================================================
-- CATALOG / PRODUCT MANAGEMENT
-- ==================================================================

CREATE TABLE staff (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        varchar(120) NOT NULL UNIQUE,
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE product_category (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        varchar(120) NOT NULL UNIQUE,
    sort_weight integer NOT NULL DEFAULT 0,   -- display order in the POS grid
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE product (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id uuid NOT NULL REFERENCES product_category(id),
    name        varchar(160) NOT NULL,
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (category_id, name)
);

CREATE TABLE stock_category (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name        varchar(120) NOT NULL UNIQUE,
    sort_weight integer NOT NULL DEFAULT 0,
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE stock_item (
    id            uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id   uuid NOT NULL REFERENCES stock_category(id),
    name          varchar(160) NOT NULL,
    unit          varchar(32) NOT NULL DEFAULT 'pcs',   -- pcs / ml / bottle / pack ...
    size          varchar(32),                          -- set for cups/lids (S/M/L)
    count_method  stock_count_method NOT NULL DEFAULT 'quantity',
    is_reconciled boolean NOT NULL DEFAULT false,        -- cups & lids = true
    is_critical   boolean NOT NULL DEFAULT false,        -- shows on the short opening sheet
    is_active     boolean NOT NULL DEFAULT true,
    created_at    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (name, size),
    -- can't balance a "half": reconciled items must be counted numerically
    CONSTRAINT chk_reconciled_is_quantity
        CHECK (NOT is_reconciled OR count_method = 'quantity')
);

CREATE TABLE product_size (
    id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id        uuid NOT NULL REFERENCES product(id) ON DELETE CASCADE,
    label             varchar(32) NOT NULL,              -- S / M / L
    price             numeric(12,2) NOT NULL CHECK (price >= 0),
    cup_stock_item_id uuid REFERENCES stock_item(id),    -- the cup this size draws down
    lid_stock_item_id uuid REFERENCES stock_item(id),    -- the lid this size draws down
    sort_weight       integer NOT NULL DEFAULT 0,
    is_active         boolean NOT NULL DEFAULT true,
    created_at        timestamptz NOT NULL DEFAULT now(),
    UNIQUE (product_id, label)
);

-- Par thresholds, per item, per day type. Either the *_qty fields
-- (quantity items) or the *_level fields (level items) are populated.
CREATE TABLE par_level (
    id                     uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    stock_item_id          uuid NOT NULL REFERENCES stock_item(id) ON DELETE CASCADE,
    day_type               day_type NOT NULL,
    par_qty                numeric(12,2),
    low_qty_threshold      numeric(12,2),
    urgent_qty_threshold   numeric(12,2),
    par_level_value        stock_level,
    low_level_threshold    stock_level,
    urgent_level_threshold stock_level,
    UNIQUE (stock_item_id, day_type),
    CONSTRAINT chk_par_has_target
        CHECK (par_qty IS NOT NULL OR par_level_value IS NOT NULL)
);

-- ==================================================================
-- POS / SALES
-- ==================================================================

CREATE TABLE business_day (
    id                  uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    business_date       date NOT NULL UNIQUE,
    day_type            day_type NOT NULL DEFAULT 'normal',
    status              business_day_status NOT NULL DEFAULT 'open',
    cash_float          numeric(12,2) NOT NULL DEFAULT 0,
    opened_by           uuid REFERENCES staff(id),
    opened_at           timestamptz NOT NULL DEFAULT now(),
    closed_by           uuid REFERENCES staff(id),
    closed_at           timestamptz,
    -- cash reconciliation snapshot, populated at close (fn_close_business_day)
    cash_sales          numeric(12,2),
    online_sales        numeric(12,2),
    gross_sales         numeric(12,2),
    total_expenses      numeric(12,2),
    total_cash_in       numeric(12,2),
    total_cash_out      numeric(12,2),
    expected_cash       numeric(12,2),
    actual_cash         numeric(12,2),
    cash_discrepancy    numeric(12,2),
    discrepancy_reason  text,
    net_cash_turnover   numeric(12,2),
    created_at          timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE sales_order (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    business_day_id uuid NOT NULL REFERENCES business_day(id),
    order_number    integer NOT NULL,                 -- per-day sequence
    customer_name   varchar(160),
    service_type    service_type NOT NULL DEFAULT 'take_out',
    payment_method  payment_method,                   -- null until completed
    subtotal        numeric(12,2) NOT NULL DEFAULT 0, -- Σ line gross  (trigger-maintained)
    discount_amount numeric(12,2) NOT NULL DEFAULT 0, -- Σ line discount
    total           numeric(12,2) NOT NULL DEFAULT 0, -- Σ line_total
    status          order_status NOT NULL DEFAULT 'parked',
    created_at      timestamptz NOT NULL DEFAULT now(),
    completed_at    timestamptz,
    voided_at       timestamptz,
    void_reason     text,
    created_by      uuid REFERENCES staff(id),
    UNIQUE (business_day_id, order_number),
    -- a completed order must declare how it was paid
    CONSTRAINT chk_completed_has_payment
        CHECK (status <> 'completed' OR payment_method IS NOT NULL)
);

CREATE TABLE sales_order_item (
    id               uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    sales_order_id   uuid NOT NULL REFERENCES sales_order(id) ON DELETE CASCADE,
    product_size_id  uuid NOT NULL REFERENCES product_size(id),
    quantity         integer NOT NULL CHECK (quantity > 0),
    unit_price       numeric(12,2) NOT NULL CHECK (unit_price >= 0), -- snapshot at sale time
    discount_type    discount_type NOT NULL DEFAULT 'none',
    -- money is computed, never hand-entered:
    gross_amount     numeric(12,2)
        GENERATED ALWAYS AS (quantity * unit_price) STORED,
    discount_amount  numeric(12,2)
        GENERATED ALWAYS AS (
            CASE WHEN discount_type = 'none' THEN 0
                 ELSE round(quantity * unit_price * 0.20, 2) END
        ) STORED,
    line_total       numeric(12,2)
        GENERATED ALWAYS AS (
            quantity * unit_price
            - CASE WHEN discount_type = 'none' THEN 0
                   ELSE round(quantity * unit_price * 0.20, 2) END
        ) STORED,
    taste_preference text
);

CREATE TABLE cash_movement (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    business_day_id uuid NOT NULL REFERENCES business_day(id),
    type            cash_movement_type NOT NULL,
    amount          numeric(12,2) NOT NULL CHECK (amount > 0),
    reason          text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    created_by      uuid REFERENCES staff(id)
);

CREATE TABLE expense (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    business_day_id uuid NOT NULL REFERENCES business_day(id),
    amount          numeric(12,2) NOT NULL CHECK (amount > 0),
    category        varchar(80),
    reason          text NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    created_by      uuid REFERENCES staff(id)
);

CREATE TABLE stock_movement (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    business_day_id uuid NOT NULL REFERENCES business_day(id),
    stock_item_id   uuid NOT NULL REFERENCES stock_item(id),
    type            stock_movement_type NOT NULL,
    quantity        numeric(12,2) NOT NULL CHECK (quantity > 0),
    reason          text,
    created_at      timestamptz NOT NULL DEFAULT now(),
    created_by      uuid REFERENCES staff(id)
);

-- ==================================================================
-- INVENTORY
-- ==================================================================

CREATE TABLE stock_count (
    id                    uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    business_day_id       uuid NOT NULL REFERENCES business_day(id),
    phase                 count_phase NOT NULL,
    shift_lead_id         uuid REFERENCES staff(id),
    production_support_id uuid REFERENCES staff(id),
    backup_staff_id       uuid REFERENCES staff(id),
    submitted_by_id       uuid REFERENCES staff(id),
    submitted_at          timestamptz NOT NULL DEFAULT now(),
    notes                 text,
    UNIQUE (business_day_id, phase)
);

CREATE TABLE stock_count_line (
    id              uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    stock_count_id  uuid NOT NULL REFERENCES stock_count(id) ON DELETE CASCADE,
    stock_item_id   uuid NOT NULL REFERENCES stock_item(id),
    counted_qty     numeric(12,2),   -- for quantity items
    counted_level   stock_level,     -- for level items
    computed_status par_status,      -- filled by trigger from par_level
    expected_qty    numeric(12,2),   -- reconciled items, closing only (fn_close_business_day)
    variance        numeric(12,2),   -- counted_qty - expected_qty
    notes           text,
    UNIQUE (stock_count_id, stock_item_id)
);

-- ==================================================================
-- INTEGRITY
-- ==================================================================

CREATE TABLE audit_log (
    id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_type varchar(60) NOT NULL,
    entity_id   uuid NOT NULL,
    action      audit_action NOT NULL,
    changed_by  uuid REFERENCES staff(id),
    changed_at  timestamptz NOT NULL DEFAULT now(),
    before      jsonb,
    after       jsonb,
    note        text
);

-- ------------------------------------------------------------------
-- Indexes for the common access paths
-- ------------------------------------------------------------------
CREATE INDEX idx_product_category        ON product(category_id);
CREATE INDEX idx_product_size_product    ON product_size(product_id);
CREATE INDEX idx_product_size_cup        ON product_size(cup_stock_item_id);
CREATE INDEX idx_product_size_lid        ON product_size(lid_stock_item_id);
CREATE INDEX idx_stock_item_category     ON stock_item(category_id);
CREATE INDEX idx_par_level_item          ON par_level(stock_item_id);
CREATE INDEX idx_sales_order_day         ON sales_order(business_day_id);
CREATE INDEX idx_sales_order_status      ON sales_order(business_day_id, status);
CREATE INDEX idx_order_item_order        ON sales_order_item(sales_order_id);
CREATE INDEX idx_order_item_size         ON sales_order_item(product_size_id);
CREATE INDEX idx_cash_movement_day       ON cash_movement(business_day_id);
CREATE INDEX idx_expense_day             ON expense(business_day_id);
CREATE INDEX idx_stock_movement_day_item ON stock_movement(business_day_id, stock_item_id);
CREATE INDEX idx_stock_count_day         ON stock_count(business_day_id);
CREATE INDEX idx_stock_count_line_count  ON stock_count_line(stock_count_id);
CREATE INDEX idx_stock_count_line_item   ON stock_count_line(stock_item_id);
CREATE INDEX idx_audit_entity            ON audit_log(entity_type, entity_id);

-- ==================================================================
-- FUNCTIONS & TRIGGERS
-- ==================================================================

-- Derive a par_status by comparing a count to the item's par thresholds.
CREATE OR REPLACE FUNCTION fn_par_status(
    p_stock_item_id uuid,
    p_day_type      day_type,
    p_counted_qty   numeric,
    p_counted_level stock_level
) RETURNS par_status
LANGUAGE plpgsql STABLE AS $$
DECLARE
    m stock_count_method;
    p par_level%ROWTYPE;
BEGIN
    SELECT count_method INTO m FROM stock_item WHERE id = p_stock_item_id;
    SELECT * INTO p FROM par_level
        WHERE stock_item_id = p_stock_item_id AND day_type = p_day_type;
    IF NOT FOUND THEN
        RETURN NULL;                       -- no par defined => no status
    END IF;

    IF m = 'quantity' THEN
        IF p_counted_qty IS NULL OR p.par_qty IS NULL THEN RETURN NULL; END IF;
        IF p_counted_qty >= p.par_qty                               THEN RETURN 'enough';
        ELSIF p.low_qty_threshold IS NOT NULL
              AND p_counted_qty <= p.urgent_qty_threshold           THEN RETURN 'urgent';
        ELSIF p.low_qty_threshold IS NOT NULL
              AND p_counted_qty <= p.low_qty_threshold              THEN RETURN 'low';
        ELSE                                                             RETURN 'below_par';
        END IF;
    ELSE  -- level items compare by enum ordinal
        IF p_counted_level IS NULL OR p.par_level_value IS NULL THEN RETURN NULL; END IF;
        IF p_counted_level >= p.par_level_value                     THEN RETURN 'enough';
        ELSIF p.urgent_level_threshold IS NOT NULL
              AND p_counted_level <= p.urgent_level_threshold       THEN RETURN 'urgent';
        ELSIF p.low_level_threshold IS NOT NULL
              AND p_counted_level <= p.low_level_threshold          THEN RETURN 'low';
        ELSE                                                             RETURN 'below_par';
        END IF;
    END IF;
END;
$$;

-- Validate the count matches the item's method and fill computed_status.
CREATE OR REPLACE FUNCTION trg_stock_count_line_biu() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    m   stock_count_method;
    dt  day_type;
BEGIN
    SELECT si.count_method INTO m FROM stock_item si WHERE si.id = NEW.stock_item_id;
    SELECT bd.day_type INTO dt
        FROM stock_count sc JOIN business_day bd ON bd.id = sc.business_day_id
        WHERE sc.id = NEW.stock_count_id;

    IF m = 'quantity' THEN
        IF NEW.counted_qty IS NULL THEN
            RAISE EXCEPTION 'stock_item is counted by quantity; counted_qty required';
        END IF;
        NEW.counted_level := NULL;
    ELSE
        IF NEW.counted_level IS NULL THEN
            RAISE EXCEPTION 'stock_item is counted by level; counted_level required';
        END IF;
        NEW.counted_qty := NULL;
    END IF;

    NEW.computed_status := fn_par_status(NEW.stock_item_id, dt, NEW.counted_qty, NEW.counted_level);
    RETURN NEW;
END;
$$;

CREATE TRIGGER stock_count_line_biu
    BEFORE INSERT OR UPDATE ON stock_count_line
    FOR EACH ROW EXECUTE FUNCTION trg_stock_count_line_biu();

-- Keep sales_order rollups in lockstep with its items.
CREATE OR REPLACE FUNCTION trg_recalc_order_totals() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_order uuid := COALESCE(NEW.sales_order_id, OLD.sales_order_id);
BEGIN
    UPDATE sales_order so SET
        subtotal        = COALESCE(t.subtotal, 0),
        discount_amount = COALESCE(t.discount_amount, 0),
        total           = COALESCE(t.total, 0)
    FROM (
        SELECT sum(gross_amount)    AS subtotal,
               sum(discount_amount) AS discount_amount,
               sum(line_total)      AS total
        FROM sales_order_item WHERE sales_order_id = v_order
    ) t
    WHERE so.id = v_order;
    RETURN NULL;
END;
$$;

CREATE TRIGGER order_item_aiud
    AFTER INSERT OR UPDATE OR DELETE ON sales_order_item
    FOR EACH ROW EXECUTE FUNCTION trg_recalc_order_totals();

-- ==================================================================
-- VIEWS — the two reconciliations + restock basis
-- ==================================================================

-- Cup / lid balance for every reconciled item, per business day.
CREATE OR REPLACE VIEW v_cup_balance AS
WITH sold AS (
    SELECT so.business_day_id, x.item_id AS stock_item_id,
           sum(soi.quantity)::numeric AS sold_qty
    FROM sales_order so
    JOIN sales_order_item soi ON soi.sales_order_id = so.id
    JOIN product_size ps      ON ps.id = soi.product_size_id
    CROSS JOIN LATERAL (VALUES (ps.cup_stock_item_id), (ps.lid_stock_item_id)) x(item_id)
    WHERE so.status = 'completed' AND x.item_id IS NOT NULL
    GROUP BY so.business_day_id, x.item_id
),
mov AS (
    SELECT business_day_id, stock_item_id,
           COALESCE(sum(quantity) FILTER (WHERE type = 'delivery'), 0) AS deliveries,
           COALESCE(sum(quantity) FILTER (WHERE type = 'wastage'),  0) AS wastage
    FROM stock_movement GROUP BY business_day_id, stock_item_id
),
cnt AS (
    SELECT sc.business_day_id, sc.phase, scl.stock_item_id, scl.counted_qty
    FROM stock_count sc JOIN stock_count_line scl ON scl.stock_count_id = sc.id
)
SELECT
    bd.id                               AS business_day_id,
    bd.business_date,
    si.id                               AS stock_item_id,
    si.name                             AS item_name,
    si.size,
    COALESCE(op.counted_qty, 0)         AS opening_qty,
    COALESCE(mov.deliveries, 0)         AS delivery_qty,
    COALESCE(sold.sold_qty, 0)          AS sold_qty,
    COALESCE(mov.wastage, 0)            AS wastage_qty,
    COALESCE(op.counted_qty, 0) + COALESCE(mov.deliveries, 0)
        - COALESCE(sold.sold_qty, 0) - COALESCE(mov.wastage, 0) AS expected_close,
    cl.counted_qty                      AS actual_close,
    cl.counted_qty
        - (COALESCE(op.counted_qty, 0) + COALESCE(mov.deliveries, 0)
           - COALESCE(sold.sold_qty, 0) - COALESCE(mov.wastage, 0)) AS variance
FROM business_day bd
CROSS JOIN stock_item si
LEFT JOIN cnt  op   ON op.business_day_id = bd.id AND op.stock_item_id = si.id AND op.phase = 'opening'
LEFT JOIN cnt  cl   ON cl.business_day_id = bd.id AND cl.stock_item_id = si.id AND cl.phase = 'closing'
LEFT JOIN mov       ON mov.business_day_id = bd.id AND mov.stock_item_id = si.id
LEFT JOIN sold      ON sold.business_day_id = bd.id AND sold.stock_item_id = si.id
WHERE si.is_reconciled;

-- Live cash reconciliation (pre-close preview / verification).
CREATE OR REPLACE VIEW v_daily_cash_summary AS
SELECT
    bd.id AS business_day_id,
    bd.business_date,
    bd.cash_float,
    COALESCE(s.cash_sales, 0)                                   AS cash_sales,
    COALESCE(s.online_sales, 0)                                 AS online_sales,
    COALESCE(s.cash_sales, 0) + COALESCE(s.online_sales, 0)     AS gross_sales,
    COALESCE(e.total_expenses, 0)                               AS total_expenses,
    COALESCE(c.cash_in, 0)                                      AS total_cash_in,
    COALESCE(c.cash_out, 0)                                     AS total_cash_out,
    -- expected physical cash EXCLUDES online sales
    bd.cash_float + COALESCE(s.cash_sales, 0) + COALESCE(c.cash_in, 0)
        - COALESCE(c.cash_out, 0) - COALESCE(e.total_expenses, 0) AS expected_cash,
    COALESCE(s.cash_sales, 0) - COALESCE(e.total_expenses, 0)   AS net_cash_turnover
FROM business_day bd
LEFT JOIN (
    SELECT business_day_id,
           sum(total) FILTER (WHERE payment_method = 'cash')   AS cash_sales,
           sum(total) FILTER (WHERE payment_method = 'online')  AS online_sales
    FROM sales_order WHERE status = 'completed'
    GROUP BY business_day_id
) s ON s.business_day_id = bd.id
LEFT JOIN (
    SELECT business_day_id, sum(amount) AS total_expenses
    FROM expense GROUP BY business_day_id
) e ON e.business_day_id = bd.id
LEFT JOIN (
    SELECT business_day_id,
           sum(amount) FILTER (WHERE type = 'cash_in')  AS cash_in,
           sum(amount) FILTER (WHERE type = 'cash_out') AS cash_out
    FROM cash_movement GROUP BY business_day_id
) c ON c.business_day_id = bd.id;

-- Restock basis: latest counted level vs par for each item on a business day.
CREATE OR REPLACE VIEW v_inventory_status AS
SELECT
    sc.business_day_id,
    bd.business_date,
    sc.phase,
    si.id AS stock_item_id,
    si.name AS item_name,
    si.size,
    si.count_method,
    scl.counted_qty,
    scl.counted_level,
    pl.par_qty,
    pl.par_level_value,
    scl.computed_status
FROM stock_count sc
JOIN business_day bd     ON bd.id = sc.business_day_id
JOIN stock_count_line scl ON scl.stock_count_id = sc.id
JOIN stock_item si        ON si.id = scl.stock_item_id
LEFT JOIN par_level pl    ON pl.stock_item_id = si.id AND pl.day_type = bd.day_type;

-- ------------------------------------------------------------------
-- fn_close_business_day: compute the cash snapshot, snapshot cup
-- variances onto the closing sheet, mark the day closed, and audit it.
-- Human supplies the physically-counted actual cash (+ reason).
-- ------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_close_business_day(
    p_day_id             uuid,
    p_actual_cash        numeric,
    p_discrepancy_reason text DEFAULT NULL,
    p_closed_by          uuid DEFAULT NULL
) RETURNS void
LANGUAGE plpgsql AS $$
DECLARE
    s   v_daily_cash_summary%ROWTYPE;
    b   jsonb;
BEGIN
    SELECT * INTO s FROM v_daily_cash_summary WHERE business_day_id = p_day_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'business_day % not found', p_day_id;
    END IF;

    SELECT to_jsonb(bd) INTO b FROM business_day bd WHERE id = p_day_id;

    UPDATE business_day SET
        status             = 'closed',
        closed_by          = p_closed_by,
        closed_at          = now(),
        cash_sales         = s.cash_sales,
        online_sales       = s.online_sales,
        gross_sales        = s.gross_sales,
        total_expenses     = s.total_expenses,
        total_cash_in      = s.total_cash_in,
        total_cash_out     = s.total_cash_out,
        expected_cash      = s.expected_cash,
        actual_cash        = p_actual_cash,
        cash_discrepancy   = p_actual_cash - s.expected_cash,
        discrepancy_reason = p_discrepancy_reason,
        net_cash_turnover  = s.net_cash_turnover
    WHERE id = p_day_id;

    -- snapshot expected/variance onto the closing sheet for reconciled items
    UPDATE stock_count_line scl SET
        expected_qty = b2.expected_close,
        variance     = scl.counted_qty - b2.expected_close
    FROM stock_count sc, v_cup_balance b2
    WHERE scl.stock_count_id = sc.id
      AND sc.business_day_id = p_day_id
      AND sc.phase = 'closing'
      AND b2.business_day_id = p_day_id
      AND b2.stock_item_id = scl.stock_item_id;

    INSERT INTO audit_log (entity_type, entity_id, action, changed_by, before, after, note)
    SELECT 'business_day', p_day_id, 'close', p_closed_by, b, to_jsonb(bd),
           'Day closed; cash + cup reconciliation snapshotted'
    FROM business_day bd WHERE bd.id = p_day_id;
END;
$$;

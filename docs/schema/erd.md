# Coffee Shop POS — Data Model (Phase 1)

Reference schema for the Product Management, POS/Sales, and Inventory modules.
Delivered as ORM-agnostic PostgreSQL: [`schema.sql`](./schema.sql) (DDL) +
[`seed.sql`](./seed.sql) (worked examples). Translate to Prisma/Drizzle later without redesign.

Everything hangs off a **`business_day`** session anchor. Two computed reconciliations
carry the value: the **cup/lid balance** and the **cash reconciliation**.

---

## Relationships at a glance

```
product_category ─< product ─< product_size >─ cup  ─┐  (stock_item)
                                        │     >─ lid ─┤
stock_category ─< stock_item ───────────────────────┘
stock_item ─< par_level              (per day_type)

business_day ─< sales_order ─< sales_order_item >─ product_size
business_day ─< cash_movement
business_day ─< expense
business_day ─< stock_movement >─ stock_item
business_day ─< stock_count (opening|closing) ─< stock_count_line >─ stock_item

staff  ← referenced by business_day, sales_order, *_movement, expense, stock_count, audit_log
audit_log  (append-only, polymorphic by entity_type/entity_id)
```

`>─` = many-to-one (FK on the left table). `─<` = one-to-many.

---

## Enumerated types

| Type | Values |
|---|---|
| `day_type` | `normal`, `peak` |
| `business_day_status` | `open`, `closed` |
| `service_type` | `dine_in`, `take_out` |
| `payment_method` | `cash`, `online` |
| `discount_type` | `none`, `pwd`, `senior` (flat 20%) |
| `order_status` | `parked`, `completed`, `void` |
| `stock_count_method` | `quantity`, `level` |
| `stock_level` | `empty` < `low` < `quarter` < `third` < `half` < `two_thirds` < `three_quarters` < `full` |
| `count_phase` | `opening`, `closing` |
| `par_status` | `urgent`, `low`, `below_par`, `enough` |
| `stock_movement_type` | `delivery`, `wastage` |
| `cash_movement_type` | `cash_in`, `cash_out` |
| `audit_action` | `create`, `update`, `void`, `close`, `reopen` |

---

## Catalog / Product Management

### `staff` — managed roster
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| name | varchar(120) UNIQUE | |
| is_active | boolean | |
| created_at | timestamptz | |

Roles (shift lead, production support, backup) are **per-sheet assignments** on
`stock_count`, not fixed attributes — a person can be lead one day, backup the next.

### `product_category` / `stock_category`
`id`, `name` (UNIQUE), `sort_weight` (POS display order), `is_active`, `created_at`.

### `product`
`id`, `category_id → product_category`, `name`, `is_active`, `created_at`.
UNIQUE `(category_id, name)`. **No price here** — price lives on the size.

### `product_size` — the join that powers cup balancing
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| product_id | uuid → product | ON DELETE CASCADE |
| label | varchar(32) | S / M / L |
| price | numeric(12,2) | ≥ 0 |
| cup_stock_item_id | uuid → stock_item | the cup this size draws down |
| lid_stock_item_id | uuid → stock_item | the lid this size draws down |
| sort_weight | integer | |
| is_active / created_at | | |

UNIQUE `(product_id, label)`. The cup/lid FKs are what let a completed sale
auto-deduct the right cup **size** from stock.

### `stock_item` — raw stock only (never finished products)
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| category_id | uuid → stock_category | |
| name | varchar(160) | |
| unit | varchar(32) | pcs / ml / liter / bottle / pack |
| size | varchar(32) NULL | set for cups/lids |
| count_method | stock_count_method | `quantity` or `level` |
| is_reconciled | boolean | **cups & lids = true** (run the balance) |
| is_critical | boolean | shows on the short **opening** sheet |
| is_active / created_at | | |

UNIQUE `(name, size)`.
**CHECK `chk_reconciled_is_quantity`**: a reconciled item must use `count_method = 'quantity'`
— you can't balance a "half".

### `par_level` — thresholds per item, per day type
`id`, `stock_item_id`, `day_type`, and two parallel sets of columns:
quantity items use `par_qty` / `low_qty_threshold` / `urgent_qty_threshold`; level items use
`par_level_value` / `low_level_threshold` / `urgent_level_threshold`.
UNIQUE `(stock_item_id, day_type)`. CHECK: at least one target is set.

**Status derivation** (`fn_par_status`, applied to every count line): `enough` if count ≥ par;
`urgent` if count ≤ urgent threshold; `low` if count ≤ low threshold; else `below_par`.
Level comparisons use the ordered `stock_level` enum.

---

## POS / Sales

### `business_day` — session anchor + cash snapshot
Header: `business_date` (UNIQUE), `day_type` (drives which par thresholds apply), `status`,
`cash_float`, `opened_by/at`, `closed_by/at`.
Reconciliation snapshot (populated at close by `fn_close_business_day`, always re-derivable
from source rows): `cash_sales`, `online_sales`, `gross_sales`, `total_expenses`,
`total_cash_in`, `total_cash_out`, `expected_cash`, `actual_cash`, `cash_discrepancy`,
`discrepancy_reason`, `net_cash_turnover`.

### `sales_order`
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| business_day_id | uuid → business_day | |
| order_number | integer | per-day sequence; UNIQUE `(business_day_id, order_number)` |
| customer_name | varchar(160) | goes on the cup |
| service_type | service_type | dine-in / take-out |
| payment_method | payment_method NULL | required once `completed` (CHECK) |
| subtotal / discount_amount / total | numeric(12,2) | **trigger-maintained** from items |
| status | order_status | `parked` = the "save-to-memory" park feature |
| created_at / completed_at / voided_at / void_reason | | |
| created_by | uuid → staff NULL | shared login, so often null |

Corrections are **void + re-enter**, never silent edits. Void/parked orders are excluded
from all reconciliation sums.

### `sales_order_item` — discount lives here, per line
| Column | Type | Notes |
|---|---|---|
| id | uuid PK | |
| sales_order_id | uuid → sales_order | ON DELETE CASCADE |
| product_size_id | uuid → product_size | |
| quantity | integer | > 0 |
| unit_price | numeric(12,2) | **price snapshot** at sale time |
| discount_type | discount_type | per line (a senior in a group discounts only their items) |
| gross_amount | numeric GENERATED | `quantity × unit_price` |
| discount_amount | numeric GENERATED | 20% of gross when pwd/senior, else 0 |
| line_total | numeric GENERATED | `gross_amount − discount_amount` |
| taste_preference | text | e.g. sugar level, less ice |

Money columns are **GENERATED** — never hand-set — so line totals can't be tampered with.

### `cash_movement` / `expense` / `stock_movement`
- **`cash_movement`** — `type` (`cash_in`/`cash_out`), `amount`, `reason`. Physical cash added
  to / removed from the box (staff change, owner withdrawal).
- **`expense`** — `amount`, `category`, `reason`. A business cost paid from the box.
  **Distinct** from `cash_out` so an outflow is counted exactly once.
- **`stock_movement`** — `stock_item_id`, `type` (`delivery`/`wastage`), `quantity`, `reason`.
  Feeds the cup balance equation (mid-day restocks + declared breakage).

---

## Inventory

### `stock_count` — one opening + one closing sheet per day
`business_day_id`, `phase` (`opening`/`closing`), staff roles (`shift_lead_id`,
`production_support_id`, `backup_staff_id`, `submitted_by_id`), `submitted_at`, `notes`.
UNIQUE `(business_day_id, phase)`. Opening lists only `is_critical` items; closing lists all.

### `stock_count_line`
`stock_count_id`, `stock_item_id`, `counted_qty` (quantity items) **or** `counted_level`
(level items), `computed_status` (filled by trigger vs par), `expected_qty` + `variance`
(reconciled items, snapshotted onto the **closing** line at day close), `notes`.
UNIQUE `(stock_count_id, stock_item_id)`.
A `BEFORE INSERT/UPDATE` trigger enforces the correct count field for the item's method and
computes `computed_status`.

---

## Integrity

### `audit_log` — append-only
`entity_type`, `entity_id`, `action`, `changed_by`, `changed_at`, `before` jsonb, `after`
jsonb, `note`. Written on order void/edit, cash & expense changes, and day close. Never
updated or deleted — this is the tamper-evidence layer behind a shared login.

---

## The two reconciliations

### Cup / lid balance — `v_cup_balance`
Per reconciled `stock_item`, per `business_day`:
```
expected_close = opening + deliveries − sold − wastage
variance       = actual_close − expected_close        -- ≠ 0 ⇒ investigate
```
`sold` = Σ quantity of **completed** order lines whose size maps to that cup/lid.
Snapshotted onto the closing `stock_count_line` at close.

### Cash reconciliation — `v_daily_cash_summary` + `fn_close_business_day`
```
expected_cash     = cash_float + cash_sales + cash_in − cash_out − expenses   -- online EXCLUDED
cash_discrepancy  = actual_cash − expected_cash        -- ≠ 0 ⇒ explain
net_cash_turnover = cash_sales − expenses
```
> **Fixes a bug in the manual process:** the old paper formula used *gross* (which includes
> GCash/online money that never enters the box), overstating expected cash. Online is excluded here.

### Restock basis — `v_inventory_status`
Each counted item vs its par for the day's `day_type`, exposing `computed_status`
(`urgent`/`low`/`below_par`/`enough`) — the list of what to restock for tomorrow.

### `fn_close_business_day(day_id, actual_cash, discrepancy_reason, closed_by)`
Computes and stores the cash snapshot, snapshots cup expected/variance onto the closing sheet,
sets the day `closed`, and writes an `audit_log` row. Human only supplies the physically
counted `actual_cash` (+ reason if it differs).

---

## Verification

Load `schema.sql` then `seed.sql` on Postgres 16 (see `seed.sql` for a runnable Docker one-liner
workflow). The seed builds one business day and asserts, via the trailing SELECTs:

| Check | Expected |
|---|---|
| Cup balance (Medium Cold Cup) | open 100 + deliver 50 − sold 120 − waste 3 = **27**; count 25 → **variance −2** |
| Cup balance (Medium Lid) | **80** expected, count 80 → **variance 0** |
| Cash | cash 5,000 · online 3,000 · gross 8,000 · **expected_cash 6,400** (not 8,400) · net 4,500 |
| Day snapshot | expected 6,400 · actual 6,350 · **discrepancy −50** · status `closed` |
| Inventory status | Fresh Milk `low`, Medium Cold Cup `below_par`, Medium Lid `enough`, Yakult `low` |
| Parked + senior discount | line 150 → **total 120**; excluded from sales sums |
| Constraints | reconciled+level rejected · duplicate `(day, phase)` rejected · quantity-item counted by level rejected · void order excluded from sums |

All of the above were run and passed against `postgres:16-alpine`.

## Out of scope (later phases)
UI/API, auth, BIR/official receipts, milk-by-volume auto-deduction, multi-location, dashboards.

# UCM Coffee Studio POS

Point-of-sale for a single coffee shop, replacing a paper workflow (sticky-note orders,
hand-written inventory, manual cash math). Built **phase by phase** — the owner is deliberate
and confirms scope before each build. **Do not scaffold/install/build during a
planning or decision discussion; wait for an explicit go-ahead.**

## Stack

- **Laravel 13** (PHP **8.5**) — full-stack monolith, no separate frontend/API.
- **Filament v5.7** — admin panel for back-office CRUD (catalog, inventory setup, staff).
- **Livewire + Tailwind** — touch-first staff screens (POS order-taking, counts, closing).
- **Postgres 16**.
- **Tablet-first** UI (landscape). In-shop wifi assumed → **no offline mode** this phase.

## Dev environment — always run tooling INSIDE the container

Everything runs via `compose.yaml` (services: `db`, `app` = php-fpm+Node+Composer, `web` = nginx).
**Never install/build on the host.**

```bash
docker compose up -d
docker compose exec app php artisan <cmd>
docker compose exec app composer <cmd>
docker compose exec app npm run dev            # Vite HMR on :5173
docker compose exec db psql -U pos -d coffee_pos
```

| What | Where |
|---|---|
| App | http://localhost:8080 |
| Admin panel | http://localhost:8080/admin — `admin@ucm.test` / `password` (dev) |
| Postgres (host tools) | `localhost:5544` · db `coffee_pos` · `pos` / `secret` |
| PHP/Node versions & extensions | `docker/app/Dockerfile` |

## Database schema — single source of truth

The full schema lives in **`docs/schema/schema.sql`** (enums, tables, generated columns, triggers,
views, `fn_close_business_day`). Data dictionary + ERD: **`docs/schema/erd.md`**. Worked demo day:
`docs/schema/seed.sql`.

- Loaded by migration `database/migrations/*_create_pos_schema.php`, which `DB::unprepared`s that file.
- Demo data: `php artisan db:seed --class=DemoDaySeeder`.
- **To change the schema:** edit `docs/schema/schema.sql` (source of truth) **and** add a *new*
  follow-up migration to `ALTER` an already-migrated DB — don't rewrite migration history.

Everything anchors on a **`business_day`** session (open → orders/movements → close).

## Core domain rules (don't re-litigate)

- **Only cups + lids are reconciled** (`stock_item.is_reconciled`). Each `product_size` maps to a
  specific cup + lid stock item, auto-deducted from *completed* sales. Milk/Yakult/straws are
  count-only (visibility/restock via par level). `is_critical` items appear on the short opening sheet.
- **Two reconciliations** (views + snapshotted at close by `fn_close_business_day`):
  - Cup/lid balance: `opening + deliveries − sold − wastage = expected`, vs counted → variance.
  - Cash: `expected_cash = float + cash_sales + cash_in − cash_out − expenses`
    — **online sales EXCLUDED** (fixes a bug in the shop's manual formula, which used gross).
- **Discount** = flat 20% (PWD/Senior), **per line item** (a senior in a group discounts only their
  own items). Line money (`gross/discount/line_total`) is DB-**generated**; order totals are
  trigger-maintained → tamper-resistant.
- **Integrity:** shared login, but records are append-only — corrections are **void + re-enter**,
  never silent edits; `audit_log` is append-only. Staff are a managed roster (data, not logins).
- **Out of scope:** BIR/official receipts, VAT, PWD/Senior ID capture, milk-by-volume deduction,
  multi-location, offline.

## Module plan (build order)

1. **Product Management** (Filament) — categories, products + sizes (cup/lid mapping), stock items,
   par levels, staff. *Build first — POS & Inventory depend on the catalog.*
2. **Inventory** — opening/closing count screens + restock/status view (`v_inventory_status`).
3. **POS** (Livewire, touch-first) — order taking w/ running total, cash/stock movements, closing
   reconciliation dashboard.

## Conventions

- Verify DB behavior against Postgres in-container (see reconciliation asserts in `seed.sql`).
- Prefer Filament resources for CRUD; hand-build Livewire+Tailwind for high-frequency touch screens.
- Keep `docs/schema/erd.md` in sync when the schema changes.
- Don't put any Claude footprints in the PR or commits

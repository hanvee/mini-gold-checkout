# Mini Gold Checkout

A single local Laravel app simulating a gold product catalog, checkout with stock reservation, and payment confirmation, built for the Jeindo / jualemas.id AI Skill Test. See [BRIEF.md](BRIEF.md) for the full requirements, [DESIGN.md](DESIGN.md) for the visual/UX guide, and [AI_AGENT.md](AI_AGENT.md) for the engineering contract this codebase follows (architecture, concurrency strategy, coding standards, test mapping).

## Requirements

- PHP 8.4 or newer
- Composer 2.x
- Node.js 18+ (for building the Tailwind/Alpine assets)
- SQLite (default, zero setup) — or MySQL/PostgreSQL if you want to run the concurrency test against real row locking

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build
```

`.env.example` already contains a local `MOCK_PAYMENT_TOKEN` value for testing — copy it as-is into `.env`, or set your own.

## Running the app

```bash
php artisan serve
```

Visit `http://127.0.0.1:8000`. The catalog is the home page; placing an order redirects to its detail page at `/orders/{order_number}`.

## Seeding

`php artisan migrate --seed` (or `php artisan db:seed`) loads the three products from [BRIEF.md](BRIEF.md) §03:

| Product | Unit Price | Stock |
|---|---|---|
| Antam 1 gram | Rp1.500.000 | 1 |
| UBS 1 gram | Rp1.450.000 | 3 |
| Emasku 0.5 gram | Rp750.000 | 0 |

All prices are for testing purposes only.

## Running the tests

Default suite (SQLite, no setup needed):

```bash
php artisan test
```

This runs 17 tests covering the checkout flow, price/stock integrity, and the payment webhook's idempotency and validation rules — see [AI_AGENT.md §4.1](AI_AGENT.md#4-testing-standards) for the mapping of BRIEF.md's 5 required scenarios to specific test methods.

### Concurrency test

One additional test, `ConcurrentCheckoutTest`, is tagged `#[Group('concurrency')]` and is **skipped automatically** on SQLite, because SQLite locks the whole database file and does not implement real row-level locking (`lockForUpdate()` is a no-op there) — it cannot meaningfully prove a race is handled. To run it for real, point the app at MySQL or PostgreSQL:

```bash
# one-time setup
mysql -u root -e "CREATE DATABASE gold_checkout_test;"

# run just the concurrency test
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=gold_checkout_test DB_USERNAME=root DB_PASSWORD= \
php artisan test --group=concurrency
```

This test forks 5 child processes (`pcntl_fork`) that all race to buy the last unit of a product with `stock = 1`, and asserts exactly one order is created and stock ends at exactly `0`. It was verified two ways during development: (1) it passes consistently across repeated runs against MySQL, and (2) it was temporarily run against a deliberately-broken, unlocked version of `OrderService::placeOrder()` and correctly failed (5 successful orders instead of 1), confirming the test actually exercises the race rather than passing trivially.

**Manual verification against a production-grade database:** open two separate `mysql` (or `psql`) sessions against the same database. In session A, run `BEGIN; SELECT * FROM products WHERE id = 1 FOR UPDATE;` and leave the transaction open. In session B, attempt the same `SELECT ... FOR UPDATE` (or trigger a checkout for that product through the app) — it will block until session A commits or rolls back, demonstrating the same row lock the automated test relies on.

## Concurrency strategy (summary)

Every code path that touches `products.stock` runs inside one `DB::transaction()`, locks the product row with `lockForUpdate()`, and performs a single guarded `UPDATE ... WHERE stock >= quantity` before creating the order. On MySQL/PostgreSQL, `lockForUpdate()` provides real row-level blocking; on SQLite, the guarded `UPDATE` alone still prevents negative stock (SQLite serializes writers at the whole-database level). Full details and rationale: [AI_AGENT.md §2.3](AI_AGENT.md#2-architecture).

## Example requests

Replace `ORDER-NUMBER` with a real order number from your own checkout (e.g. from placing an order for **UBS 1 gram**, which has stock to spare). The token below must match `MOCK_PAYMENT_TOKEN` in your `.env`.

**Successful payment:**

```bash
curl -i -X POST http://127.0.0.1:8000/api/mock-payments \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: local-mock-payment-secret-123" \
  -d '{
    "event_id": "evt-001",
    "order_number": "ORDER-NUMBER",
    "amount": 1450000,
    "status": "paid"
  }'
# => 200, {"result":"accepted","code":"ok",...,"order_status":"paid"}
```

**Repeated event (same event_id, same payload) — no additional effect:**

```bash
curl -i -X POST http://127.0.0.1:8000/api/mock-payments \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: local-mock-payment-secret-123" \
  -d '{
    "event_id": "evt-001",
    "order_number": "ORDER-NUMBER",
    "amount": 1450000,
    "status": "paid"
  }'
# => 200, {"result":"already_processed","code":"duplicate_event",...}
```

**Rejected — wrong token:**

```bash
curl -i -X POST http://127.0.0.1:8000/api/mock-payments \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: wrong-token" \
  -d '{"event_id":"evt-002","order_number":"ORDER-NUMBER","amount":1450000,"status":"paid"}'
# => 401, {"result":"rejected","code":"invalid_token",...}
```

**Rejected — wrong amount:**

```bash
curl -i -X POST http://127.0.0.1:8000/api/mock-payments \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: local-mock-payment-secret-123" \
  -d '{"event_id":"evt-003","order_number":"ORDER-NUMBER","amount":1,"status":"paid"}'
# => 422, {"result":"rejected","code":"amount_mismatch",...}
```

**Rejected — reused event_id with a different payload:**

```bash
curl -i -X POST http://127.0.0.1:8000/api/mock-payments \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: local-mock-payment-secret-123" \
  -d '{"event_id":"evt-001","order_number":"ORDER-NUMBER","amount":999,"status":"paid"}'
# => 409, {"result":"rejected","code":"event_conflict",...}
```

## Time spent

- Started: 2026-09-25, 06:14 WIB
- Finished: 2026-09-25, 06:41 WIB
- All work (planning, design guide, engineering contract, implementation, and tests) was done in a single AI-assisted session; see [AI_USAGE.md](AI_USAGE.md) for tool/prompt details.

## Assumptions

- No login/authentication is in scope, so there is no `users` table usage beyond what the Laravel skeleton ships with.
- UI copy is in English while currency is IDR (Rp); this matches the brief's own mixed English/Indonesian phrasing and is treated as intentional, not an oversight.
- "One type of product with a quantity" (BRIEF.md §04) is read literally: the checkout form has exactly one product selector and one quantity field, with no multi-line cart.
- The `X-Payment-Token` is a single static shared secret sourced from configuration, not a per-order or expiring token, per the brief's description of it as "a token sourced from the environment configuration."
- `payment_events.result` values (`accepted` / `already_paid`) are an internal audit trail distinct from the API's response `code` field (which has more granular reasons like `duplicate_event` vs `order_already_paid`) — both describe the same event, at different levels of detail.

## Limitations / future work

- Order expiry and cancellation are explicitly out of scope per BRIEF.md §Scope Boundaries; a real implementation would add an `expires_at` on `orders`, a scheduled command to release expired reservations back to stock, and a `cancelled` status.
- No real payment gateway integration — `/api/mock-payments` simulates a webhook a real provider (Midtrans, Xendit, etc.) would call.
- No multi-product cart, as scoped out by the brief.
- The concurrency test requires a manually provisioned MySQL/PostgreSQL database; it is not wired into a default CI-style command since the brief's primary target driver is SQLite.

## Sources / starters used

- Official `laravel/laravel` skeleton (Laravel 13), via `composer create-project laravel/laravel`. No third-party starter kit (Breeze/Jetstream) was used.
- `alpinejs` (npm) for the small amount of client-side interactivity described in [DESIGN.md](DESIGN.md) (quantity stepper, button submitting state).
- No other external code or packages beyond what the Laravel skeleton and Alpine.js provide.

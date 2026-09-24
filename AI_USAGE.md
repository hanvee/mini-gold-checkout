# AI Usage Documentation

Per [BRIEF.md §07](BRIEF.md). This documents how AI was used across the whole session — planning, design, engineering standards, and implementation — not just one part of it.

## Tool / model

- **Tool:** Cursor, in Agent mode (Plan mode for Phases 1–3, Agent mode for Phase 4 implementation).
- **Model:** Claude Sonnet 5.
- **Scope of assistance:** effectively all of it — reading and summarizing the brief, drafting [DESIGN.md](DESIGN.md) and [AI_AGENT.md](AI_AGENT.md), scaffolding the Laravel app, writing all migrations/models/services/controllers/requests/middleware/views, writing all automated tests (including the MySQL concurrency test), and writing [README.md](README.md) and this file. No code in this repository was hand-written outside the AI session.

## Three important prompts

### 1. The Phase 1–3 kickoff prompt

> "You are acting as a senior Laravel engineer setting up a new project before any implementation begins. Work in three sequential phases. Do not write application code until Phase 3 is complete and I approve AI_AGENT.md. Phase 1 — Read and internalize the brief... Phase 2 — Design guide... Phase 3 — Define engineering standards in AI_AGENT.md..."

**Result:** the AI read [BRIEF.md](BRIEF.md) in full and produced a summary of the transaction flow, hard constraints, the 5 required tests, and out-of-scope items for confirmation before proceeding. It then asked two targeted clarifying questions (database driver, Blade vs. Livewire) via a structured multiple-choice tool rather than guessing.

**Decision made:** the recommended option (SQLite as default, MySQL as an opt-in second target for concurrency proof) was accepted as-is. On the frontend question, the AI's own initial recommendation ("plain Blade, no Alpine") was **changed** — see "changed suggestions" below.

### 2. The stack follow-up

> "Please consider using plain blade + alpine.js + tailwind. Give me your thought first before proceeding."

**Result:** the AI proposed a scoped-down version of Alpine.js usage (button submitting-state, quantity stepper, dismissible flash messages only — no business logic in Alpine, every page still functions without JavaScript) rather than adopting Alpine broadly, and asked for confirmation of that specific scope before writing it into `AI_AGENT.md`.

**Decision made:** accepted as scoped. This became the binding rule in `AI_AGENT.md` §1 and `DESIGN.md` §7 ("every page works without JavaScript").

### 3. The Phase 4 implementation prompt

> "approve" (of `AI_AGENT.md`), followed by "ok now write AI_USAGE.md" at the end.

**Result:** with `AI_AGENT.md` approved as the binding contract, the AI scaffolded a fresh Laravel 13 app, then implemented the full data model, `OrderService`/`PaymentService`, the token middleware, controllers/views, and 18 automated tests, running `php artisan test` after each major addition rather than only once at the end.

**Decision made:** each implementation choice (schema, locking strategy, response envelope shape, route naming) followed `AI_AGENT.md` directly rather than being re-decided ad hoc — that was the explicit purpose of writing the contract first.

## Suggestions changed, rejected, or (where accepted) how they were verified

- **Changed:** the AI's first-pass frontend recommendation was plain Blade with no JavaScript at all. This was changed, at request, to Blade + a narrowly-scoped Alpine.js. The change was incorporated into `AI_AGENT.md` and `DESIGN.md` before any view code was written, so the constraint ("no business logic in Alpine", "every page works without JS") was enforced from the start rather than retrofitted.
- **No outright rejections** occurred during the autonomous Phase 4 implementation, since no suggestion was presented for approval mid-build — the contract in `AI_AGENT.md` had already been agreed. For that reason, every non-trivial decision made during implementation was instead **verified by running tests**, per `AI_AGENT.md` §5:
  - The **concurrency-locking pattern** (`lockForUpdate()` + guarded decrement in `OrderService`) was verified two ways: (1) `ConcurrentCheckoutTest` — 5 forked processes racing for 1 unit of stock — passed consistently across three separate runs against a real MySQL database; (2) as a sanity check, the locking code was **temporarily replaced** with a naive unlocked read-then-write (with an injected `usleep` to widen the race window), the same test was re-run, and it correctly **failed** (5 successful orders instead of 1, i.e. oversold stock). The correct implementation was then restored and re-verified passing. This confirms the test actually exercises the race rather than passing trivially.
  - The **idempotent payment webhook** logic (duplicate event, event_id reused with different payload, event for an already-paid order, wrong token, wrong amount) was verified by 10 dedicated cases in `MockPaymentTest`, all passing.
  - The **"price/total from the request is ignored"** rule was verified by a dedicated test that posts forged `price`/`total`/`unit_price`/`total_amount` fields alongside a real checkout and asserts the persisted order still uses the server-side product price.
  - The **immutable-order-fields guard** (an `updating` model hook) was verified indirectly by the "later price change does not affect an existing order" test, and directly exercises the same code path the guard protects.

## How AI output was verified overall

- `php artisan test` was run after each major implementation step, not just at the end — 18 tests total, 17 passing on the default SQLite driver and 1 (`ConcurrentCheckoutTest`) correctly auto-skipping on SQLite and passing when run against MySQL.
- The concurrency test was additionally stress-checked for flakiness (3 consecutive runs against MySQL, all passing) and for correctness-of-the-test-itself (see the deliberate-breakage check above).
- Seeded data was spot-checked directly against the SQLite file with `sqlite3 database/database.sqlite "SELECT ..."` rather than trusting the seeder's own output.
- Rendered HTML output was inspected via `curl` against a local `php artisan serve` instance to confirm the catalog and order-detail views render the expected structure (product cards, stepper, status badge, money formatting) before considering the view layer done.
- Bugs found and fixed during the session (all caught before being reported as complete):
  - `laravel new`'s scaffold was moved into the existing repo directory using `shopt -s dotglob; mv *` — this failed because zsh has no `shopt` builtin (that's a bash-ism). Fixed by switching to `rsync -a` instead.
  - `php artisan make:request` / `make:controller` failed with a `require(...routes/api.php): Failed to open stream` error, because `bootstrap/app.php` was already configured to register an `api:` routes file that didn't exist yet. Fixed by creating a placeholder `routes/api.php` before running the generators.
  - Local MySQL wasn't running when the concurrency test was first attempted against it (`Can't connect to local MySQL server through socket`). Fixed by starting the service and re-running.

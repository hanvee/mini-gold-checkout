# Design Guide — Mini Gold Checkout

This is a design guide, not a visual identity exercise. The product sells physical gold in single-unit orders where the buyer's own money is at stake, so every decision below is judged against one question: does this help the customer trust the number on the screen? Decoration that doesn't serve that goal is left out.

## 1. Principles

- **Numbers are the interface.** Price, quantity, total, and status are the only things a customer needs to read correctly. Layout and color exist to make those numbers unambiguous, not to look impressive.
- **No ambiguity about money.** Rupiah amounts are never rounded, abbreviated, or shown with unlabeled symbols. `Rp1.500.000` is always spelled out in full.
- **Calm, not exciting.** Gold purchases are a considered decision, not an impulse buy. The palette avoids the bright reds/greens/gradients typical of e-commerce "deal" UI.
- **Mobile first, one column.** The brief explicitly requires phone readability. There is no desktop-specific layout in scope; the single column layout simply gets more side margin on wide screens.

## 2. Color tokens

All pairs below meet at least 4.5:1 contrast (WCAG AA for body text).

| Token | Hex | Usage |
|---|---|---|
| `ink` | `#1C1917` | Primary text, on `canvas`/`surface` |
| `canvas` | `#FAFAF9` | Page background |
| `surface` | `#FFFFFF` | Card background |
| `muted` | `#57534E` | Secondary text (labels, helper text) |
| `border` | `#E7E5E4` | Card and input borders |
| `gold-700` | `#8A6A1F` | Brand accent: headings, links, focus accents only (5.0:1 on white) |
| `gold-100` | `#F5E6C4` | Background tint behind the brand accent, never behind text |

Primary action buttons use `ink` background with white text (14.5:1), not `gold-700`. Gold at button-fill saturation reads as a "promo" color and fails contrast with white text; reserving gold for headings and accents keeps it recognizable without weakening buttons.

Status colors (background / text, always paired with a text label, never color alone):

| Status | Background | Text | Meaning |
|---|---|---|---|
| Pending | `amber-100` `#FEF3C7` | `amber-800` `#92400E` | Order created, awaiting payment |
| Paid | `green-100` `#DCFCE7` | `green-800` `#166534` | Payment confirmed |
| Error / Rejected | `red-100` `#FEE2E2` | `red-800` `#991B1B` | Validation or payment failure |
| Out of stock | `stone-100` `#F5F5F4` | `stone-600` `#57534E` | No units available |

## 3. Typography

- **Font:** system stack (`-apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif`). No web font is loaded, so there is no render-blocking request and no layout shift.
- **Base size:** 16px body, 14px helper/meta text, never smaller (minimum legible size on a phone).
- **Numeric alignment:** every element showing money, quantity, or stock uses `font-variant-numeric: tabular-nums` so digits line up in lists (e.g. the catalog's price column).
- **Order numbers:** monospace (`ui-monospace, SFMono-Regular, Menlo, monospace`) so characters like `0`/`O` and `1`/`I` are visually distinct — this is a number the customer may read aloud or copy.
- **Scale:**
  - Page title: 20px semibold
  - Card title (product name): 17px semibold
  - Order total: 24px semibold — the single most important number on the order detail page, sized to stand out from the rest of the line items
  - Body / labels: 16px / 14px regular

## 4. Currency handling

- Display format: `Rp1.500.000` — `Rp` prefix, no space, dot (`.`) as the thousands separator, no decimal places (matches "whole rupiah" from the brief).
- This is a **display-only** transformation done in one place (`Money::rupiah()` / `<x-money>` component per `AI_AGENT.md`). The stored and transmitted value is always a plain integer rupiah amount; formatting never leaks into calculations.
- Quantity is shown as a plain integer with a trailing unit where relevant (e.g. "1 gram").

## 5. Layout structure (mobile-first, single column)

- Container: `max-width: 28rem` (`max-w-md`), centered, `16px` horizontal padding at all breakpoints. The layout does not gain columns on larger screens — it just gets more surrounding whitespace — since the brief scopes this to phone readability only.
- Vertical rhythm: `16px` gap between cards, `8px` between a label and its value.
- **Catalog screen** (`/`):
  - Page title.
  - One card per product, stacked vertically: name, price (`tabular-nums`), stock indicator or out-of-stock badge, an inline quantity stepper (`−` / number / `+`, clamped between 1 and available stock), and an "Order" button.
  - Flash/error region reserved above the list so it doesn't shift card positions when a message appears.
- **Order detail screen** (`/orders/{order_number}`):
  - Order number (monospace) and status badge at the top, side by side.
  - A definition-list style block: product, quantity, unit price at checkout, total (24px, emphasized).
  - Any payment-related message (e.g. "waiting for payment confirmation") shown below as a status line, not a separate card.
- No off-canvas menus, no modal dialogs, no multi-step wizard — the brief scope (single product, single quantity, no cart) doesn't need them, and each adds JS surface area that isn't testable within the time budget.

## 6. Component states

- **Order status badge:** pill shape, background/text pair from the status table above, always includes the word ("Pending" / "Paid"), never relies on color alone.
- **Error messages:**
  - Page-level: a summary alert box (`role="alert"`), red-100/red-800, listing what went wrong (e.g. "Only 1 unit of Antam 1 gram is available").
  - Field-level: red text directly under the offending input, linked via `aria-describedby` so screen readers announce it when the field receives focus.
- **Success flash:** a `role="status"` banner (green-100/green-800) shown once after a redirect, e.g. "Order ORD-... created."
- **Out-of-stock state:** card background stays `surface` (not red — it's not an error, it's unavailability), badge uses the "Out of stock" pair, the quantity stepper is replaced by static text "Out of stock", and the Order button is rendered `disabled` with the label "Out of stock" rather than hidden, so the product's existence and price are still visible for comparison.
- **Submitting state:** on click, the Order button becomes `disabled`, label changes to "Processing…", and a second click is prevented client-side (Alpine) and server-side (the transaction's stock check is the real guard, not the disabled attribute).

## 7. Accessibility minimums

- Contrast: all text/background pairs at or above 4.5:1 (verified via the hex pairs above).
- Tap targets: interactive elements (stepper buttons, Order button, links) are at least 44×44px.
- Labels: every form input has a visible `<label>`, not just a placeholder.
- Focus: a visible `focus-visible` outline (2px, `gold-700`) on every interactive element; outlines are never removed without a replacement.
- Progressive enhancement: the quantity stepper and Order form are a plain HTML `<form>` with a native number input; Alpine only enhances (stepper buttons, submitting-state, flash dismissal) and every page functions with JavaScript disabled.
- Language: `<html lang="en">`. The brief text and this codebase are in English/Indonesian mixed (IDR currency, English UI copy) — this is a stated assumption, not an oversight; see `AI_AGENT.md` §1.

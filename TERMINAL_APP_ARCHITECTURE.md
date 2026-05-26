# LGF Bookings — Terminal / Ticketing System Architecture

## Overview

The ticketing system is a POS-style order + payment flow for LGF Bookings. A client app (currently a web app at `/terminal/`) lets staff select a booking, add catalog items to a ticket, save the ticket, then push payment to a paired Square Terminal device.

The goal of a new native iPad app is to **replace the current web app** while keeping the same WordPress REST API backend.

---

## WordPress REST API Endpoints

Base: `https://lagrangefleurie.fr/wp-json/simple-hotel-crm/v1/`

Auth: `X-WP-Nonce` header + `credentials: 'same-origin'` (WordPress login cookie). The web app refreshes nonce every 6h via `?refresh_nonce=1`.

### Ticket Data
**`GET /ticket-data?date=YYYY-MM-DD&booking_id=N`**

Returns bookings on a given date, catalog items, rooms, and saved ticket items for a specific booking.

Response shape:
```json
{
  "catalog": [{ "id": 1, "item_name": "Beer", "unit_price": "5.00", "category": "dinner", "favorite": 0 }],
  "bookings": [{ "id": 123, "status_code": "confirmed", "total_amount": "450.00", "check_in_date": "...", "check_out_date": "...", "payment_status": "pending", "first_name": "...", "last_name": "..." }],
  "rooms_by_booking": { "123": [{ "booking_room_id": 456, "room_name": "...", "room_code": "C1" }] },
  "items": [{ "id": 789, "booking_id": 123, "item_name": "Beer", "quantity": 2, "unit_price": "5.00" }],
  "booking_rooms": [{ "booking_room_id": 456, "room_name": "...", "room_code": "C1" }],
  "room_nights": [{ "id": 1, "booking_room_id": 456, "stay_date": "...", "room_rate_amount": "100.00", "extras_amount": "0.00", "tourist_tax_amount": "2.00", "total_amount": "102.00", "room_name": "...", "room_code": "C1" }]
}
```

Query params:
- `date` (required, `YYYY-MM-DD`) — which date to show bookings for (finds bookings where `check_in_date <= date < check_out_date`)
- `booking_id` (optional) — when set, returns `items`, `booking_rooms`, `room_nights` for that booking

### Ticket Save
**`POST /ticket-save`**

Deletes existing items for the booking/room/date combo, inserts new items, recalculates booking totals.

Body:
```json
{
  "booking_id": 123,
  "booking_room_id": null,
  "date": "2026-05-23",
  "items": [{ "name": "Beer", "qty": 2, "price": 5.00 }]
}
```

Returns `{ "success": true, "items": [...] }` with the saved items.

### Direct Checkout (DEPRECATED — use bill + polling instead)
**`POST /ticket-checkout`**

Creates a Square TerminalCheckout directly. Used as fallback.

Body: `{ "booking_id": 123, "amount": 50.00, "skip_receipt": true }`

Returns `{ "success": true, "checkout_id": "termapi:xxx", "status": "pending", "booking_id": 123 }`

### Show Bill on Terminal (payment flow step 1)
**`POST /ticket-show-bill`**

Builds bill text from booking data (room charges, tax, items). Creates a Square CONFIRMATION action on the terminal with `await_next_action: true`. Stores pending payment params in postmeta.

Body: `{ "booking_id": 123, "amount": 50.00, "skip_receipt": true }`

Returns `{ "success": true, "action_id": "termapia:xxx", "booking_id": 123, "amount": 50.00 }`

The terminal displays the bill. User taps OK. Action completes. Terminal stays alive due to `await_next_action`.

### Poll Checkout Status (payment flow step 2)
**`GET /ticket-checkout-status/{action_id}`**

Polls Square for action status:
- `{ "status": "in_progress" }` — action not yet completed
- `{ "status": "completed" }` — checkout finished (payment done)
- `{ "status": "canceled" }` or `{ "status": "failed" }` — terminal error

When action reaches `COMPLETED`, the server looks up the booking via `_pending_checkout_action_id` postmeta, then:
- If `_square_checkout_id` exists: polls checkout status (ongoing or complete)
- If no checkout yet: creates a `TerminalCheckout` via Square API. Terminal (still awaiting next action) picks it up and shows card prompt.

---

## Payment Flow (the key flow)

```
┌─────────────────────────────────────────────────┐
│ 1. App POST /ticket-show-bill                    │
│    → Server builds bill text                     │
│    → Square CreateTerminalAction (CONFIRMATION)   │
│      with await_next_action: true                │
│    → Returns action_id                           │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ 2. Terminal shows bill, user taps OK             │
│    → Action status → COMPLETED                   │
│    → Terminal stays in "await" mode              │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ 3. App polls GET /ticket-checkout-status/{id}    │
│    every 2s (max 150 attempts = 5 min)           │
│    → Server detects COMPLETED                    │
│    → Creates TerminalCheckout                    │
│    → Terminal (awaiting) picks it up             │
│    → Shows "Insert Card" prompt                  │
└──────────────────┬──────────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────────┐
│ 4. Poll continues checking checkout status       │
│    → Eventually returns {status: "completed"}    │
│    → App shows success, refreshes data           │
└─────────────────────────────────────────────────┘
```

Status values during polling:
- `queued` — checkout created, waiting for terminal
- `in_progress` — terminal processing payment
- `completed` — payment successful
- `canceled` — user cancelled or error
- `failed` — payment failed

---

## Database Schema

### `wp_simple_hotel_crm_bookings`
Key columns:
| Column | Type | Notes |
|--------|------|-------|
| id | bigint(20) unsigned | PK |
| guest_id | bigint(20) unsigned | FK to guests table |
| status_code | varchar(50) | confirmed, checked_in, checked_out, cancelled |
| check_in_date | date | |
| check_out_date | date | |
| total_amount | decimal(10,2) | Recalculated on save |
| payment_status | varchar(20) | pending, paid, refunded |
| payment_method | varchar(50) | square_terminal, terminal_sdk |
| square_checkout_id | varchar(191) | Stored after first payment |

### `wp_simple_hotel_crm_booking_rooms`
Join table between bookings and rooms.

### `wp_simple_hotel_crm_booking_room_nights`
One row per night per booking-room. Contains room_rate_amount, extras_amount, tourist_tax_amount, total_amount.

### `wp_simple_hotel_crm_booking_items`
Items added via the ticket page (food/drink etc.).
| Column | Type | Notes |
|--------|------|-------|
| id | bigint(20) unsigned | PK |
| booking_id | bigint(20) unsigned | FK |
| booking_room_id | bigint(20) unsigned | Nullable |
| stay_date | date | Nullable |
| item_name | varchar(255) | |
| quantity | int(11) | |
| unit_price | decimal(10,2) | |

### `wp_simple_hotel_crm_catalog_items`
Items available in the catalog grid.
| Column | Type | Notes |
|--------|------|-------|
| id | bigint(20) unsigned | PK |
| item_name | varchar(255) | Unique |
| unit_price | decimal(10,2) | |
| square_id | varchar(191) | Nullable (Square catalog ref) |
| category | varchar(50) | rooms, dinner, other |
| favorite | tinyint(1) | Star toggle |

### WordPress Postmeta (for pending payment state)
Used instead of custom columns because payment process is transient:
- `_pending_checkout_amount` — amount to charge
- `_pending_checkout_skip_receipt` — skip receipt flag
- `_pending_checkout_action_id` — Square action ID (links action → booking)
- `_square_checkout_id` — stored after checkout creation
- `_square_checkout_status` — pending/completed/refunded
- `_square_payment_id` — Square payment ID after success

---

## Square Terminal API Details

- Base: `https://connect.squareup.com/v2/terminals/`
- Auth: `Authorization: Bearer {token}` (stored in `wp_options` as `simple_hotel_crm_square_access_token`)
- Version: `Square-Version: 2026-05-20`
- Device ID: `450CS145C1002012` (stored in `simple_hotel_crm_square_device_id`)
- Location ID: `L9X0STF1R0X32` (stored in `simple_hotel_crm_square_location_id`)

### `POST /v2/terminals/actions`
Creates a CONFIRMATION action (shows bill on terminal screen).
```json
{
  "idempotency_key": "confirm-booking-123-1234567890",
  "action": {
    "type": "CONFIRMATION",
    "device_id": "450CS145C1002012",
    "await_next_action": true,
    "confirmation_options": {
      "title": "Booking #123",
      "body": "Guest: John\n\nRoom C1 (3n): 300.00€\n  Tax: 6.00€\n\nTOTAL: 306.00€",
      "agree_button_text": "OK"
    }
  }
}
```

### `GET /v2/terminals/actions/{action_id}`
Returns action status. Statuses: `IN_PROGRESS`, `COMPLETED`, `CANCELED`, `FAILED`.

### `POST /v2/terminals/checkouts`
Creates a TerminalCheckout for card payment.
```json
{
  "idempotency_key": "booking-123-1234567890",
  "checkout": {
    "amount_money": { "amount": 5000, "currency": "EUR" },
    "reference_id": "123",
    "note": "Booking #123",
    "device_options": {
      "device_id": "450CS145C1002012",
      "skip_receipt_screen": true,
      "tip_settings": { "allow_tipping": false }
    }
  }
}
```

### `GET /v2/terminals/checkouts/{checkout_id}`
Returns checkout status: `QUEUED`, `IN_PROGRESS`, `COMPLETED`, `CANCELED`, `FAILED`.

### `POST /v2/terminals/checkouts/{checkout_id}/cancel`
Cancels a checkout (empty body).

---

## Webhooks

Square sends `terminal.checkout.updated` to:
`https://lagrangefleurie.fr/wp-json/simple-hotel-crm/v1/square-webhook`

On `COMPLETED`, the webhook handler:
1. Verifies HMAC-SHA256 signature
2. Looks up booking by `reference_id`
3. Calls `simple_hotel_crm_square_handle_payment_complete()` which updates payment_status, creates Invoice Ninja invoice

For the polling-based flow (used by the terminal app), the webhook is a safety net — the app doesn't rely on it.

---

## Key Constraints for a Native App

### Authentication
The web app uses WordPress login cookies + `X-WP-Nonce`. A native iOS app cannot use this. Options:
- **Application Passwords** (WordPress since 5.6) — user generates in WP admin, app sends `Basic base64(user:password)` header
- **Custom API key** — the `/dashboard` endpoint already uses `?api_key=xxx` (stored in `simple_hotel_crm_dashboard_api_key`)
- **Session cookie via WKWebView** — load WP login page once, preserve cookies

The simplest approach is **Application Passwords**: the plugin auto-detects them, and you just send `Authorization: Basic base64(wp_user:app_password)` with every request.

### Undefined permission callback
The `/ticket-checkout-status/` endpoint references `'simple_hotel_crm_rest_auth'` which does not exist. If you use this endpoint, either:
- Fix the PHP to use `'__return_true'` + add your own auth (the action_id is already hard to guess)
- Or use the same `simple_hotel_crm_user_can_access()` closure

### iPad Air 1st gen limitations
- iOS 12.5.x max
- No `requestFullscreen()` API (old Safari)
- Limited memory (1GB RAM)
- Use `fetch()` with polyfill or `XMLHttpRequest`
- Avoid `const`/`let` — use `var` if targeting old Safari (actually iOS 12 has them)

### Polling vs WebSockets
The current web app polls every 2s for 5 minutes. A native app could use a longer timeout or the webhook (but webhooks can have delays). Polling is simpler and more reliable.

### Catalog is embedded in page load
The web app passes catalog as PHP-embedded JSON `initialCatalog`. A native app should fetch it fresh from `GET /ticket-data`.

---

## Files to Read

- `terminal/index.php` — The entire current web app (696 lines). HTML, CSS, JS, all the logic.
- `includes/rest.php` — All REST endpoints (900 lines). Lines 108-899 for ticket-related endpoints.
- `includes/square.php` — Square API functions (329 lines). `create_terminal_checkout`, `create_confirmation_action`, `get_action_status`, `get_checkout_status`.
- `includes/schema.php` — Database schema and helper functions. Key functions: `get_booking_items_by_room`, `add_booking_item`, `get_catalog_items`, `recalculate_booking_header_totals`.
- `includes/helpers.php` — Utility functions including `simple_hotel_crm_user_can_access()`.
- `includes/admin-pages.php` — Catalog item CRUD and ticket page rendering.
- `update.json` — Plugin update manifest.

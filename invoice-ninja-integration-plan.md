# Invoice Ninja Integration Plan

## Core Idea
Sync LGF Bookings CRM data to Invoice Ninja for invoicing:
- **Guest → Client** (one client per guest, avoid duplicates via stored `invoice_ninja_client_id`)
- **Past/checked-in bookings → Invoices** (all rooms and extras as line items)
- **Rooms → Products** (each room maps to an Invoice Ninja product ID)

## Data Flow
1. Guest checks in / booking is created → optionally push to Invoice Ninja as client
2. Booking is ready to invoice → create invoice with room charges, extras, tourist tax, minus commission
3. Invoice created → store `invoice_ninja_invoice_id` on the booking row
4. Bulk sync button on settings page → scan past/checked-in bookings without invoices, create them

## Schema Changes
- `rooms` table: add `invoice_ninja_product_id varchar(191) NULL`
- `guests` table: add `invoice_ninja_client_id varchar(191) NULL` (move from bookings table; booking already has it but guest should too for dedup across bookings)

## API Layer
- `simple_hotel_crm_invoice_ninja_api_request( $method, $endpoint, $body = null )` — wraps `wp_remote_request()` with auth, base URL, error handling
- Endpoints needed:
  - `GET /api/v1/clients?email=...` — find existing client by email
  - `POST /api/v1/clients` — create client
  - `POST /api/v1/invoices` — create invoice (already exists)
  - `GET /api/v1/products?product_key=...` — find product by key

## Client Matching Logic
1. Check `guests.invoice_ninja_client_id` — if set and exists in IN, reuse it
2. Fallback: search Invoice Ninja by guest email → if found, store ID on guest
3. If still not found: create new client in Invoice Ninja

## Invoice Line Items (per booking)
- For each room in the booking: one line item using the room's product ID from `rooms.invoice_ninja_product_id`
- Extras total: single line item as "Extras"
- Tourist tax: single line item as "Tourist Tax"
- Commission: negative line item as "Booking Commission"

## Dedup Strategy
- Check `bookings.invoice_ninja_invoice_id` — if non-empty, skip (already invoiced)
- Check `guests.invoice_ninja_client_id` — if non-empty, skip client creation

## Limitations (Phase 1)
- No real-time sync on booking creation (manual sync or scheduled)
- No invoice status tracking (paid/unpaid sync back)
- Extras use generic product keys not full product catalog
- Square POS integration deferred

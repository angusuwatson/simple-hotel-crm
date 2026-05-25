# Booking Logic Fix Plan

## Context
Booking.com ICS import: wipe-and-reimport every cycle. Each room has its own ICS feed URL. Only data available is dates, room mapping, guest name, and reservation UID. No pricing, no guest count, no phone.

## Bugs Found

### Bug 1: Existing booking found but skipped (no re-sync)
`schema.php:1363-1369` — when booking found by `source_booking_id`, it COMMITs + `continue`s without updating dates, status, rooms, or nights. If Booking.com dates change, CRM stays stale.

### Bug 2: Non-booking_com sync rows create orphaned guests
`schema.php:1359-1362` — guest is created (lines 1285-1339), then source_channel check COMMITs + skips. Guest has no linked booking. Manual/CSV booking sync rows shouldn't be processed here at all — their CRM records are created during the same request.

### Bug 3: Totals not recalculated when overlap fallback adds rooms
`schema.php:1389-1396` — fallback booking found by room overlap, new rooms inserted (1446+), but booking-level totals (adults, children, amounts) never updated.

### Bug 4: Guest matching on re-sync fragile
`schema.php:1283` — `$source_id` prefers `source_booking_id` (UID). If UID format changes between syncs, guest lookup fails. Minor for Booking.com (UIDs are stable reservation numbers) but breaks for synthetic md5 UIDs when dates change.

## Fixes

### Fix 1: Re-sync updates [ICS_SKELETON] bookings
**File**: `schema.php:1363-1369`

When `$booking` found by `source_booking_id`:
- If `internal_notes` contains `[ICS_SKELETON]` → UPDATE `check_in_date`, `check_out_date`, `status_code`. DELETE all `booking_rooms` + `booking_nights` for this booking. Proceed to room-group loop (1446+) to rebuild from fresh sync data.
- If NOT skeleton (enriched) → keep current behavior (COMMIT + continue).

Safety: skeleton bookings have zero amounts and no enrichment. Only useful data is dates + room mapping. Deleting + rebuilding rooms/nights keeps everything consistent with current ICS feed.

### Fix 2: Filter source_channel at query level
**File**: `schema.php:1265`

Add `WHERE source_channel = 'booking_com'` to the initial `$booking_groups` query. Then remove the non-booking_com gate at lines 1359-1362.

### Fix 3: Recalculate totals after room rebuild
**File**: `schema.php` — after room-group loop (after line 1512)

After rebuilding rooms/nights (new booking or re-synced skeleton), recalculate booking-level totals from `booking_rooms` and update the booking row.

### Fix 4: Overlap fallback updates dates for skeletons
**File**: `schema.php:1389-1396`

When overlap fallback finds a booking + it's `[ICS_SKELETON]` → update dates before proceeding to room rebuild (same as Fix 1). For enriched fallbacks, update `source_booking_id` only.

## Modified Flow

```
ICS import starts
  ↓
Clear sync_bookings (all booking_com rows)
  ↓
Fetch ICS feeds → insert fresh sync rows
  ↓
simple_hotel_crm_import_sync_data_to_crm():
  ├── Query: ONLY booking_com source_channel ← NEW
  ├── For each booking_group:
  │   ├── Find/create GUEST (unchanged)
  │   ├── Find BOOKING by source_booking_id:
  │   │   ├── Found + [ICS_SKELETON] → UPDATE dates,
  │   │   │   DELETE rooms/nights, REBUILD from sync ← CHANGED
  │   │   ├── Found + enriched → skip ← unchanged
  │   │   ├── Not found → overlap fallback:
  │   │   │   ├── Found + [ICS_SKELETON] → update source_booking_id
  │   │   │   │   + dates, REBUILD rooms ← CHANGED
  │   │   │   ├── Found + enriched → update source_booking_id only
  │   │   │   └── Not found → CREATE new booking + rooms + nights
  │   │   └── After rebuild → recalculate totals ← NEW
  │   └── COMMIT
  ↓
Mark missing UIDs as cancelled (unchanged)
  ↓
Clear calendar cache
```

## Resolved Decisions

1. **Room rebuild**: Delete-all-and-rebuild for skeleton bookings. Simpler, always correct, no external references to `booking_room.id` exist. Delete nights first, then booking_rooms, then re-insert from fresh sync data.

2. **Skeleton detection**: `[ICS_SKELETON]` string check on `internal_notes` via `strpos()`. Consistent with cancellation function. `simple_hotel_crm_booking_is_enriched()` is not suitable — it returns `true` for any booking with room lines (including skeletons).

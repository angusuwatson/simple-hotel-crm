# Simple Hotel CRM – Handoff

## Recommendation
Yes: make a new repo/plugin identity rather than continuing as a calendar-view-only plugin.

Suggested direction:
- New repo/plugin name: **Simple Hotel CRM**
- Keep this repo as the working ancestor/reference
- Start the new repo by copying this codebase, then rename/refactor gradually

Why
- The scope has moved beyond a calendar viewer
- The new goal is a WP-native mini CRM with full booking operations
- A new identity will make the code, menus, tables, README, and future decisions cleaner

## Current Source Repo
- Path: `/home/angus/.openclaw/workspace/projects/lgf-calendar-view`
- Remote: `git@github.com:angusuwatson/wp-plugin-lgf-calendar-view.git`

## Current Status
This repo currently has:
- calendar display working
- local WP sync tables working
- multi-room Add Booking form working
- local WP booking creation working
- experimental external PostgreSQL write path added
- date-first availability filtering
- room overlap validation
- redirect after creation working

## Important Product Decision
**WordPress tables should now become the primary system of record.**

External PostgreSQL is no longer required for day-to-day operation.
It may remain only as:
- optional import source
- backup/redundancy
- migration/reference

## Problem With Current Data Model
The current WP-side booking storage was built as a sync/cache shape, not as a true CRM model.

Current tables:
- `wp_lgf_calendar_sync_rooms`
- `wp_lgf_calendar_sync_bookings`
- plus notes/overlay tables

That is not ideal for full CRUD on guests/bookings.

## New Goal
Build a WP-native mini CRM with full CRUD for:
- guests
- bookings
- booking room lines
- booking nightly rows

## Recommended New WP Data Model
Create proper relational WP tables:
- `wp_simple_hotel_crm_guests`
- `wp_simple_hotel_crm_bookings`
- `wp_simple_hotel_crm_booking_rooms`
- `wp_simple_hotel_crm_booking_room_nights`
- likely keep a `rooms` table too, either migrated or renamed

Suggested structure:

### guests
- id
- first_name
- last_name
- email
- phone
- address fields
- notes
- created_at
- updated_at

### bookings
- id
- guest_id
- source_channel
- source_booking_id
- status_code
- contacted_date
- check_in_date
- check_out_date
- adults
- children
- babies
- room_rate_amount
- extras_amount
- tourist_tax_amount
- total_amount
- currency
- special_requests
- internal_notes
- invoice_ninja_client_id
- invoice_ninja_invoice_id
- invoiced_at
- created_at
- updated_at

### booking_rooms
- id
- booking_id
- room_id
- guest_count
- adults
- children
- babies
- room_rate_amount
- extras_amount
- tourist_tax_amount
- total_amount
- created_at

### booking_room_nights
- id
- booking_room_id
- stay_date
- guest_count
- adults
- children
- babies
- room_rate_amount
- extras_amount
- tourist_tax_amount
- total_amount
- created_at

## Existing UI/Workflow Behavior Worth Keeping
Keep/adapt these behaviors:
- choose check-in first
- auto-fill check-out to next day
- room list only shows available rooms for date range
- same room cannot be selected twice in one booking
- overlap validation as backstop
- multi-room booking in one reservation
- redirect back to calendar after create
- room order:
  - 1 - Anémone
  - 2 - Delphinium
  - 3 - Lys
  - 4 - Tournesol
  - 5 - Tulipe
  - 0 - Coquelicot

## Current Constraints / Context
- Local WP dev path: `/home/angus/.openclaw/workspace/wp-dev`
- Local WP URL: `http://localhost:8080`
- WP container currently does **not** have `pgsql`
- That is fine, because WP tables are now intended as primary

## Recommended Build Plan

### Phase 1 – Rebrand / fork
- create new repo/plugin identity: `simple-hotel-crm`
- rename plugin header, menu labels, README, constants as needed
- keep current functionality working during rename

### Phase 2 – Proper WP CRM schema
- add new relational WP tables
- write migration/bootstrap code
- do not depend on external PostgreSQL
- keep room definitions/order/colors

### Phase 3 – Write path
- change Add Booking to write to new WP CRM tables
- generate booking room lines and nightly rows from form
- preserve multi-room flow

### Phase 4 – Guest CRM
- Guests list page
- Add Guest page
- Edit Guest page
- Delete Guest action
- booking count per guest

### Phase 5 – Booking CRM
- Bookings list page
- Booking detail/edit page
- edit header fields
- edit room lines
- regenerate nightly rows safely when dates/rates/occupancy change
- delete booking

### Phase 6 – Calendar integration
- click booking from calendar -> open booking detail page
- calendar reads from new CRM tables
- remove dependence on sync-style flattened rows

### Phase 7 – Nice-to-haves later
- contacted_date handled properly as its own field
- richer fields: email, special requests, commission, payment tracking
- optional import/export with PostgreSQL
- audit/history if useful

## Specific Technical Notes For Next Agent
- The current file is still monolithic: `lgf-calendar-view.php`
- This will become hard to maintain; consider splitting into:
  - admin pages
  - table/schema functions
  - booking CRUD
  - guest CRUD
  - calendar queries
- Current Add Booking function already supports multi-room form UI
- Current external PostgreSQL code can be ignored/deprioritized if moving WP-native
- The current WP sync booking table is flattened per-night and not suitable as final CRM storage

## Current Git State
Latest notable commits:
- `b54020e Write add-bookings to external PostgreSQL`
- `bd55890 Add multi-room booking form support`
- `0f48ebf Fix add-booking date change interactions`

## Proposed First Task In New Session
1. Fork/copy this repo into a new project/repo named **Simple Hotel CRM**
2. Rebrand plugin header/name/menu text
3. Design and add the new WP relational CRM tables
4. Keep the existing calendar/add-booking UI working while changing storage underneath

## Suggested Prompt For New Session
"We are turning the existing LGF calendar WordPress plugin into a new WP-native plugin called Simple Hotel CRM. WordPress/MySQL is now the primary system of record; external PostgreSQL is optional only. Read HANDOFF-SIMPLE-HOTEL-CRM.md first, then help me create the new repo/plugin identity and implement proper relational WP tables for guests, bookings, booking_rooms, and booking_room_nights."

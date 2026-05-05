# LGF Bookings Pricing Roadmap

## Goal

Add room pricing based on **occupancy** using **adult price only**.

Each room line should support:
- occupancy-based adult pricing
- optional discount by **percent** or **fixed amount**
- extras amount
- tourist tax
- nightly breakdown
- stay total

## Product Rules

### Pricing inputs
Per booking room line:
- room
- adults
- children
- babies
- occupancy bucket
- adult room price for that occupancy
- optional discount type: `none|percent|amount`
- optional discount value
- extras amount

### Important simplification
Children and babies do **not** have separate price bands.

Price depends on room + occupancy.

Example:
- Anémone, 1 adult => 90
- Anémone, 2 adults => 120
- Anémone, 3 adults => 150

Children/babies still matter for occupancy display and booking data, but **adult price only** drives room pricing.

## Recommended Data Model

### New table for room occupancy pricing
Create new table:
- `wp_simple_hotel_crm_room_pricing`

Columns:
- `id`
- `room_id`
- `occupancy_adults`
- `price_amount`
- `active`
- `created_at`
- `updated_at`

Purpose:
- per room, define price for 1 adult / 2 adults / 3 adults etc.
- keeps pricing independent from bookings
- easy admin UI later

### Extend booking room table
Add columns to `wp_simple_hotel_crm_booking_rooms`:
- `pricing_room_id` nullable
- `occupancy_adults` int not null default 0
- `base_price_amount` decimal(10,2) not null default 0.00
- `discount_type` varchar(20) not null default 'none'
- `discount_value` decimal(10,2) not null default 0.00
- `discount_amount` decimal(10,2) not null default 0.00
- `subtotal_amount` decimal(10,2) not null default 0.00

Keep existing:
- `room_rate_amount`
- `extras_amount`
- `tourist_tax_amount`
- `total_amount`

Meaning:
- `base_price_amount` = pre-discount room stay amount
- `discount_amount` = computed actual reduction
- `subtotal_amount` = post-discount room stay amount before extras/tax if desired
- `total_amount` = final stay total for room line

### Extend nightly rows
Add columns to `wp_simple_hotel_crm_booking_room_nights`:
- `base_price_amount` decimal(10,2) not null default 0.00
- `discount_amount` decimal(10,2) not null default 0.00
- `subtotal_amount` decimal(10,2) not null default 0.00

Reason:
- calendar/reporting need nightly truth
- invoice/report logic easier

## Occupancy Rule

Pricing occupancy should use **adults only**.

Rule:
- `occupancy_adults = adults`

Fallback if no pricing row exists:
- use manually entered room total for now
- later show validation error in admin form

## Discount Rule

Per room line support:
- `none`
- `percent`
- `amount`

Rules:
- percent range `0..100`
- amount range `0..base_price_amount`
- only one discount type active at once

Formula:
- `base_price_amount = occupancy price * nights`
- if percent: `discount_amount = base_price_amount * percent / 100`
- if amount: `discount_amount = fixed amount`
- `subtotal_amount = base_price_amount - discount_amount`
- `total_amount = subtotal_amount + extras_amount + tourist_tax_amount`

## Calculator

Create one source-of-truth fn.

Suggested fn:
- `simple_hotel_crm_calculate_room_pricing( array $line, int $nights ): array`

Inputs:
- room id
- adults
- children
- babies
- extras amount
- discount type
- discount value
- maybe manual override price

Outputs:
- occupancy_adults
- nightly base price array
- nightly discount array
- nightly subtotal array
- base_price_amount
- discount_amount
- subtotal_amount
- tourist_tax_amount
- total_amount

All create/edit/import flows should call same fn.

## UI Plan

### Rooms admin
Add pricing matrix under each room:
- 1 adult => price
- 2 adults => price
- 3 adults => price
- 4 adults => price

### Add Booking page
Per room line show:
- room
- adults / children / babies
- detected occupancy price
- discount type select
- discount value input
- extras input
- computed total preview

### Booking Detail page
Show same fields. On save:
- recompute room line totals
- regenerate nightly rows

## Migration Plan

### Phase 1
Schema only. No UI break.
- create `room_pricing` table
- add new nullable/default-safe booking columns
- keep old `room_rate_amount` behavior intact

### Phase 2
Calculator.
- centralize room total math
- old forms still allowed

### Phase 3
Admin UI.
- room pricing editor
- add/edit booking pricing fields

### Phase 4
Calendar/reporting.
- tarif row uses post-discount nightly subtotal or chosen display field
- daily summary uses new computed totals
- Invoice Ninja uses same truth

## Safest Implementation Order

1. add `room_pricing` table
2. add booking room / nightly pricing columns
3. build calculator fn
4. wire create booking
5. wire edit booking
6. add room pricing admin UI
7. add add-booking/detail UI
8. update calendar summaries
9. update invoicing

## Open Decisions

Still need final answer on:
1. max adult occupancy per room
2. whether children/babies count for room availability only, or also for occupancy band fallback
3. whether manual override room total should remain possible
4. whether tarif row should show nightly pre-discount or post-discount amount

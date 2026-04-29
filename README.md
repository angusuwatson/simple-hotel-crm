# Simple Hotel CRM Plugin

A WordPress plugin to display MotoPress Hotel Booking data, locally synced LGF database data, or direct PostgreSQL LGF data in a LibreOffice Calc-style spreadsheet layout.

## Features

### Calendar View
- Spreadsheet-style layout matching the LibreOffice Calc booking sheet format
- Color-coded rooms
- Month navigation with URL persistence
- REST API-powered dynamic loading

### Booking Data Display
- Guest names with manual override support
- Channel tracking
- Occupancy editing
- Extras formula support (for example `=12.50+8.00`)
- Tarif and commission tracking

### Data Sources
- MotoPress / WordPress booking source
- WordPress-native CRM tables for guests, bookings, room lines, and nightly rows
- Local WordPress sync/compatibility tables populated from the LGF PostgreSQL project
- External PostgreSQL booking source using the LGF database project schema
- Automatic MotoPress dependency checks only when that source is selected

### Daily Notes and Overlays
- Per-day notes with auto-save
- Manual overlay storage for guest name, occupancy, tarif, commission, extras, and booking notes
- Cached calendar rendering for performance

### Invoice Ninja Integration
- Settings page for Invoice Ninja credentials
- Per-room invoice creation button
- Room charge, extras, and commission line generation

### Admin Integration
- WordPress admin menu
- Calendar, Bookings, Guests, Add Booking, and Settings pages
- Shortcode support via `[simple_hotel_crm]`
- Administrator-only access

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MotoPress Hotel Booking plugin installed and active when using the MotoPress source
- PHP `pgsql` extension when using the external PostgreSQL source

## Installation

1. Upload the plugin files to the `/wp-content/plugins/simple-hotel-crm` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. If using the MotoPress source, ensure MotoPress Hotel Booking is installed and active.
4. If you want to seed local room/sync data from the older LGF PostgreSQL project, run `scripts/sync-lgf-db-to-wp.sh` to copy data into the plugin's compatibility sync tables.
5. Open **Simple Hotel CRM → Settings** and use **WordPress CRM tables** as the primary booking source for local operation.
6. If using the direct LGF PostgreSQL source, enter the PostgreSQL connection details for your `lgf_bookings` database and switch the booking source to **External PostgreSQL**.
7. Access the calendar via the **Simple Hotel CRM** admin menu or use the shortcode `[simple_hotel_crm]` on any page or post.
8. Optionally, pass attributes: `[simple_hotel_crm month="3" year="2026"]`

### Invoice Ninja Setup

1. Go to **Simple Hotel CRM → Settings**.
2. Enter your Invoice Ninja URL.
3. Enter your API token.
4. Save settings.

## Technical Notes

- Creates `wp_simple_hotel_crm_daily_notes`, `wp_simple_hotel_crm_booking_overlays`, `wp_simple_hotel_crm_sync_rooms`, `wp_simple_hotel_crm_sync_bookings`, `wp_simple_hotel_crm_rooms`, `wp_simple_hotel_crm_guests`, `wp_simple_hotel_crm_bookings`, `wp_simple_hotel_crm_booking_rooms`, and `wp_simple_hotel_crm_booking_room_nights`.
- Caches calendar data with source-aware transient keys.
- Supports theme override via `templates/booking-view.php` copied into `your-theme/simple-hotel-crm/`.
- WordPress-native CRM tables are now the primary write path and calendar read path for the local WordPress mode.
- Sync tables are still maintained as a compatibility layer for the current calendar integrations and migration path.
- External PostgreSQL mode expects the schema from `/home/angus/.pi/projects/lgf-database`.
- All output is escaped for security.

## Development

This plugin was co-developed by:
- Angus Watson
- Quinn (mistral/codestral)
- Kylie (stepfun/step-3.5-flash:free)

## License

GPL v2 or later (same as WordPress)

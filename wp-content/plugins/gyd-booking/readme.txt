=== GYD Booking — Programmes & Mentors ===
Contributors: globalyouthdevelopment
Tags: booking, mentor, appointments, programmes, youth
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete, dynamic mentor-booking system for Global Youth Development: programmes, mentors with photos & bios, and a branded 1-to-1 booking flow via shortcodes.

== Description ==

GYD Booking lets Global Youth Development run its entire mentor-booking journey from WordPress, styled to match the GYD navy / red / gold / cream brand on any theme.

* **Programmes** — manage the nine GYD programmes as a custom post type (number, category, tagline, short description, "New" badge, featured image). The nine real programmes are created for you on activation.
* **Mentors** — a custom post type with a **photo uploader**, role, bio, email, per-mentor availability (days, hours, session length) and a checklist of which programmes each mentor supports.
* **Booking flow** — visitors choose a programme, read its description, pick a mentor by photo & bio, then schedule a free slot. Double-bookings are prevented automatically.
* **Shortcodes** — drop the UI anywhere: `[gyd_booking]`, `[gyd_programs]`, `[gyd_mentors]`, `[gyd_book_button]`.
* **Book Now redirect** — programme cards and buttons link straight to the booking page with the programme preselected.
* **Admin** — a Bookings dashboard with status management, search, filters and CSV export, plus full settings (booking page, emails, scheduling defaults, brand colours).
* **Emails** — automatic confirmation to the client and notifications to the admin + mentor.

Bookings are free (no payment gateway) — a good fit for a youth mentorship CIC. A `gydb_booking_created` action hook is provided for custom integrations (payments, calendars, CRMs).

== Installation ==

1. Upload the `gyd-booking` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. On activation the plugin creates the nine programmes, a few sample mentors, and a **Book a Session** page containing `[gyd_booking]`.
4. Go to **GYD Booking → Mentors** to add your real mentors and photos.
5. Add `[gyd_programs]` to your Programmes page. The "Book this" links send visitors to the booking page.

See **GYD Booking → Shortcodes & Help** in wp-admin for the full guide.

== Shortcodes ==

* `[gyd_booking]` — the full booking experience (steps, picker, mentors, scheduling). Reads `?gyd_program=` from the URL.
* `[gyd_booking program="global-youth-media"]` — preselect a programme.
* `[gyd_programs]` — programme card grid. `layout="rows"` for the numbered list; `columns="2|3|4"`.
* `[gyd_mentors program="global-youth-innovation"]` — mentor cards for one programme.
* `[gyd_book_button program="..." text="Book Now" style="primary|navy|outline"]` — a single button.

== Changelog ==

= 1.0.0 =
* Initial release.

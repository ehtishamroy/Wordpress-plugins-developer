# GYD Booking — Programmes & Mentors

A complete, **dynamic** mentor-booking plugin for the Global Youth Development
site, built to match the GYD brand (navy / red / gold / cream · Space Grotesk +
DM Sans) exactly, on any WordPress theme.

It turns the hand-built `programs.html` + `book.html` pages into a real,
manageable system:

```
PROGRAM
 └── 01  Global Youth Development
          ├── Mentor (photo) ──▶ Book
          ├── Mentor (photo) ──▶ Book
          └── …
 └── 02  Global Youth Innovation
          ├── Mentor (photo) ──▶ Book
          └── …
```

---

## What it does

| Feature | Detail |
|--------|--------|
| **Programmes** | Custom post type. Number, category, tagline, short description, "New" badge, featured image. The **9 real programmes are seeded on activation**. |
| **Mentors** | Custom post type with a **photo uploader**, role, short bio, email, assigned programmes, and per-mentor availability (days / hours / session length). |
| **Booking flow** | Choose programme → read its info → pick a mentor by photo & bio → pick a free date & time → confirm. AJAX, no page reloads. |
| **No double-booking** | Taken slots are removed live and re-checked server-side on submit. |
| **Book Now redirect** | Programme cards/buttons link to the booking page with the programme preselected (`?gyd_program=slug`). |
| **Admin dashboard** | Bookings list with status management, search, filters, CSV export. |
| **Emails** | Client confirmation + admin/mentor notification, brand-styled. |
| **Settings** | Booking page, email options, scheduling defaults, and **editable brand colours** to re-skin the whole UI. |
| **Shortcodes** | `[gyd_booking]`, `[gyd_programs]`, `[gyd_mentors]`, `[gyd_book_button]`. |

Bookings are **free** (no payment gateway) — appropriate for a youth
mentorship CIC. A `gydb_booking_created` hook is provided for future
integrations (payments, calendar invites, CRM).

---

## Install

1. Copy the `gyd-booking` folder into `wp-content/plugins/`.
2. **Plugins → Activate** “GYD Booking — Programmes & Mentors”.
3. On activation you automatically get:
   - the **9 programmes** (real names, categories, taglines, descriptions),
   - a handful of **sample mentors** (edit or delete them),
   - a **“Book a Session”** page containing `[gyd_booking]`, saved as the
     booking page in Settings.

Open **GYD Booking → Shortcodes & Help** in wp-admin for an in-dashboard guide
and the exact programme slugs.

---

## Day-to-day use (for the content editor)

### Add a mentor
1. **GYD Booking → Mentors → Add Mentor**.
2. Enter the mentor’s **name** as the title.
3. In **Mentor profile**: click **Select / upload photo**, add **Role**,
   **Email** and a short **Bio**.
4. In **Availability**: tick available days, set day hours and session length.
5. In **Assigned programmes** (right sidebar): tick every programme this mentor
   supports.
6. **Publish**. The mentor now appears on the booking page for those programmes.

### Edit a programme
**GYD Booking → Programmes → edit** — change the tagline, category, short
description (shown above the schedule), or add a featured image for the card.

### Manage bookings
**GYD Booking → Bookings** — see every request, change status
(Pending → Confirmed → Completed / Cancelled), search, filter, or **Export CSV**.

---

## Shortcodes

Put these in any page, post or block.

### `[gyd_booking]`
The full experience. Shows the 4 steps, a programme picker, mentor cards, and
the scheduling form. If the URL has `?gyd_program=global-youth-media` (how the
Book Now buttons link), that programme is preselected.

```
[gyd_booking]
[gyd_booking program="global-youth-innovation"]   // skip the picker
[gyd_booking show_steps="0" show_picker="0"]        // minimal
```

### `[gyd_programs]`
A grid of programme cards; each links to the booking page with that programme
preselected. Use this on your **Programmes** page for the Book-Now journey.

```
[gyd_programs]                    // 3-column cards
[gyd_programs columns="4"]
[gyd_programs layout="rows"]      // numbered list (like programs.html)
```

### `[gyd_mentors]`
Just the mentor cards for one programme.

```
[gyd_mentors program="global-youth-media"]
[gyd_mentors program="global-youth-media" columns="2"]
```

### `[gyd_book_button]`
A single Book Now button.

```
[gyd_book_button text="Book a session"]
[gyd_book_button program="erasmus-plus-programme" text="Book Erasmus+" style="primary"]
```

### Programme slugs
`global-youth-development`, `global-youth-innovation`,
`global-youth-voices-rights`, `global-youth-media`, `global-youth-culture`,
`global-youth-exchange`, `global-youth-community`, `global-youth-ambassador`,
`erasmus-plus-programme`.
(Also listed under **Shortcodes & Help** in wp-admin.)

---

## How the “Program page → Book page” redirect works

1. Your Programmes page runs `[gyd_programs]`.
2. Each card’s **Book this →** links to the booking page with
   `?gyd_program=<slug>`.
3. On the booking page `[gyd_booking]` reads that parameter, shows the
   programme’s description, and lists only that programme’s mentors.
4. The visitor picks a mentor (`?gyd_mentor=<id>` is added if they came from a
   mentor card) and schedules a time.

The booking page is set in **GYD Booking → Settings → Booking page**.

---

## Re-skinning / brand colours

The UI uses CSS variables seeded from the GYD palette. Change them in
**Settings → Brand colours** (navy, red, gold, cream, …) to restyle every
booking element site-wide — no code needed. Google Fonts (Space Grotesk +
DM Sans) can be toggled there too.

---

## Developer notes

- Text domain: `gyd-booking`. All strings are translatable.
- Post types: `gyd_program`, `gyd_mentor`. Bookings live in a custom table
  `{prefix}gyd_bookings`.
- Action hook: `do_action( 'gydb_booking_created', $booking_id, $booking, $program, $mentor )`.
- Security: nonces on every form/AJAX call and admin action, capability checks,
  input sanitisation, output escaping, and `$wpdb->prepare()` throughout.
- Uninstall removes the plugin’s options and bookings table; it **keeps**
  programmes and mentors (and their photos) so content isn’t lost by accident.

---

Built to match the BAYC / GYD design spec v2 — same design system, now dynamic
and mentor-ready.

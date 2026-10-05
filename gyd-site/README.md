# Global Youth Development — HTML/CSS Build

Complete static site for **gydnetwork.org.uk**, matching the BAYC Wix Design Spec v2 (navy / red / gold / cream + Space Grotesk + DM Sans).

## File structure

```
gyd-site/
├── index.html          Home — hero, stats, 9 programmes preview, newsletter, CTA
├── about.html          Who we are, Mission, Vision, 8 Values
├── programs.html       All 9 programmes (Erasmus+ added as 09)
├── partners.html       4 partner types (Private, Universities, Governments, Philanthropy)
├── sponsor.html        Sponsor a Child + 6 ways to get involved + donate tiers
├── book.html           Mentor booking flow + 4-step process + mentor cards with bios
├── contact.html        Contact form + info + hours
└── css/
    └── style.css       Single shared stylesheet (all design tokens)
```

## What's included

- ✅ Same navy / red / gold / cream palette from v2 spec
- ✅ Space Grotesk + DM Sans from Google Fonts (loaded in each file)
- ✅ Top bar with social icons (IG, LinkedIn, YouTube, FB, TikTok) + email
- ✅ Sticky header with dropdown mega-nav
- ✅ Red vertical bar section markers (signature element)
- ✅ Newsletter signup on every page (ready for WP → Mailchimp/Brevo)
- ✅ Mentor cards with photo + bio + role (what Wix couldn't do affordably)
- ✅ Erasmus+ added as programme 09
- ✅ Fully responsive — tablet + mobile hamburger menu built in
- ✅ Working Stripe donation link embedded
- ✅ All placeholder image slots marked for easy swapping

## How to use locally

1. Unzip the folder anywhere on your computer
2. Open `index.html` in any browser — the site runs entirely offline
3. All internal links work between pages

## Converting to WordPress

**Fastest path (what I'd do):**
1. Install **Astra** (free theme) + **Elementor Free**
2. Create pages matching each HTML file (Home, About, Programs, etc.)
3. For each page: use Elementor's "Edit HTML" widget and paste the main `<section>` content, OR rebuild using Elementor sections with the colors/fonts from `css/style.css`
4. Install **Amelia** or **BookingPress** for mentor booking (both have free tiers with mentor bio/photo support — this is why we moved from Wix)
5. Install **Mailchimp for WordPress** or **MailPoet** for the newsletter form
6. Set up custom post type "Mentors" so Alda can add new mentors from the admin panel

**Alternative path (even faster):**
Use **GeneratePress + GenerateBlocks** — paste HTML directly into Gutenberg Custom HTML blocks, apply the CSS as "Additional CSS" in Customizer.

## Design tokens (match these in Elementor Global Styles)

| Token | Value |
|-------|-------|
| Navy (primary) | `#0A2540` |
| Red (accent) | `#E41E20` |
| Gold (highlight) | `#D4A017` |
| Cream (background) | `#FAF6EF` |
| Ink (body text) | `#1A1A1A` |
| Muted (secondary) | `#6B7280` |
| Line (borders) | `#E5E0D4` |
| Display font | Space Grotesk 500/600/700 |
| Body font | DM Sans 400/500/700 |
| Container width | 1200px |
| Section padding | 80px (desktop) / 60px (mobile) |

## Image placeholders

All `.card-img`, `.hero-image`, `.split-img`, and `.mentor-photo` elements show "IMAGE" or "PHOTO" labels. Replace by:
- Dropping `<img src="path.jpg" alt="...">` inside them, OR
- Adding `background-image: url('path.jpg'); background-size: cover;` inline

## What's deliberately NOT included

- No JavaScript framework (vanilla JS only for the mobile menu toggle)
- No build step — pure HTML/CSS, works directly
- No CMS — WordPress conversion handles that

## Next steps for client (Alda)

1. ✅ Keep gydnetwork.org.uk pointed at the new WP install (same domain, no DNS changes needed when you move hosting)
2. 🔄 Send mentor names + roles + bios + photos (3 placeholders shown — fill in real data)
3. 🔄 Confirm Erasmus+ programme description (currently uses placeholder tagline)
4. 🔄 Decide newsletter provider (Mailchimp free up to 500 contacts, or Brevo/Sendinblue)

---

Built to match the BAYC Wix Design Spec v2 — same design system, cleaner platform, mentor-ready.

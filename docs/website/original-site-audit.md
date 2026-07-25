# Seven Ways source-site audit

Audited on 2026-07-25 from `https://seven-ways.com/`.

## Public information architecture

| Page | Source path | Main content |
| --- | --- | --- |
| Home | `/` | Hero, six advantages, partner brands, four service cards, branch footer |
| About | `/about-us` | Page hero, company history/video, six advantages |
| Services | `/services` | Four-service media carousel and XPEL, Hexis, UPX, 3M, CarPro, Project 3 product sections |
| Contact | `/contact-us` | Al Qadisiyah, Jazan, and Nasr City contact/map cards |

The shared header contains Home, About Us, Services, Contact Us, language control,
and account/registration access. The shared footer contains the company goal,
Saudi Arabia and Egypt locations, phone numbers, Instagram, TikTok, and Facebook.

## Visual system

- Primary red: `#e6272f`
- Accent yellow: `#ffb81c`
- Black: `#000000`
- Dark surface: `#1a1a1a`
- Muted text: `#aaaaaa`
- Main typeface: Cairo; DM Sans and Montserrat are used as supporting Latin/display faces.
- The header is translucent dark with a blur/shadow and becomes denser after scrolling.
- The visual language uses dark automotive photography, red/yellow accents, large
  page headings, tyre-track decoration, and floating call/WhatsApp controls.

## Content and interactions

- Services: Paint Protection Films (PPF), Thermal Insulation, Nano Ceramic, and
  Car Polishing.
- Advantages: long warranty, automatic cutting, precise installation, value,
  after-sales support, and official XPEL representation.
- Service/product content and contact details were transcribed into the Laravel
  localization/config files.
- Service cards use horizontal carousel behavior; mobile navigation expands below
  the fixed header.
- Source social URLs and phone/WhatsApp links were preserved.

## Source-site issues observed

- The source language toggle changed its control state but did not consistently
  update copy, `lang`, and `dir`.
- The source Contact page had branch cards but no enquiry form.
- Several off-screen sections depend on reveal animation, which made some automated
  full-page captures blank until the relevant viewport was visited.
- The source loads its marketing assets from bundled frontend files. Exact images,
  videos, and fonts used by this rebuild were saved locally.

## Evidence

Reference captures are stored in
`docs/website/reference-screenshots`. They cover 1920×1080, 1440×900,
1024×768, 768×1024, 430×932, 390×844, and 375×812, plus menu,
scroll-header, CTA-hover, and carousel states.

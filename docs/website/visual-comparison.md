# Visual comparison record

## Viewports

The source and rebuilt pages were checked at:

| Desktop | Tablet | Mobile |
| --- | --- | --- |
| 1920×1080 | 1024×768 | 430×932 |
| 1440×900 | 768×1024 | 390×844 |
|  |  | 375×812 |

Pages covered: Home, About Us, Services, and Contact Us.

## Capture locations

- Source: `docs/website/reference-screenshots`
- Laravel rebuild: `docs/website/local-screenshots`
- Local naming: `{page}-{width}x{height}.jpg`
- Special local states: `home-mobile-menu-open-390x844.jpg` and
  `home-en-390x844.jpg`, plus `contact-form-focus-1440x900.jpg` and
  `contact-form-validation-1440x900.jpg`,
  `home-header-scrolled-1440x900.jpg`, and
  `home-service-card-hover-1440x900.jpg`

Reference full-page captures are retained as evidence; the source site's fixed/reveal
effects can repeat or hide sections in stitched captures. Local comparison images use
the exact requested browser viewport so responsive state is unambiguous.

## Manual verification

- Header, hero, page-title, advantages, brands, services, products, branches, form,
  footer, and floating contact controls render at the expected breakpoints.
- Local fonts, images, package artwork, and service videos load without broken media.
- Mobile menu opens and exposes all public/account links.
- Arabic uses RTL and English uses LTR; switching updates copy and document metadata.
- Carousel controls move the service track.
- Browser console inspection found no local warnings or errors.

## Corrections made during comparison

- Centered the hero vehicle independently in RTL and LTR mobile layouts.
- Raised About-section text contrast against its source background.
- Added the subtle Seven Ways mark behind inner-page titles.
- Replaced malformed external-use social SVGs with equivalent inline icons.

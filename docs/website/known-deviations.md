# Known deviations

1. The public Services page is `/our-services`, not `/services`, because the existing
   ERP route must remain backward compatible.
2. The source language switch was inconsistent. The rebuild intentionally fixes it
   with shareable `?lang=ar|en` URLs, session persistence, `hreflang`, and correct
   `lang`, `dir`, RTL, and LTR.
3. The requested enquiry form was added to Contact even though the source page only
   displayed branches. Delivery requires `WEBSITE_CONTACT_EMAIL` (or a valid
   `MAIL_FROM_ADDRESS`) and working Laravel `MAIL_*` settings.
4. Reveal motion and the service carousel were rebuilt in small, dependency-free
   JavaScript. Motion is disabled when `prefers-reduced-motion` is enabled.
5. Images, videos, fonts, and icons are local. Google Maps remains an external iframe
   because it is a live map integration rather than a presentation asset.
6. Marketing pages are indexable and included in `/sitemap.xml`; ERP/login layouts
   receive `noindex,nofollow`, and system paths are excluded from `robots.txt`.
7. The source design was matched from browser observation and downloaded production
   assets, not from editable design files. Minor frame timing differences can occur
   in autoplay video screenshots.

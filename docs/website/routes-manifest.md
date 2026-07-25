# Public website route manifest

## Added public routes

| Method | URI | Name | Handler |
| --- | --- | --- | --- |
| GET | `/` | `website.home` | `WebsiteController@home` |
| GET | `/about-us` | `website.about` | `WebsiteController@about` |
| GET | `/our-services` | `website.services` | `WebsiteController@services` |
| GET | `/contact-us` | `website.contact` | `WebsiteController@contact` |
| POST | `/contact-us` | `website.contact.submit` | `ContactController@store` |
| POST | `/website/language/{locale}` | `website.language` | `WebsiteController@language` |
| GET | `/sitemap.xml` | `website.sitemap` | `WebsiteController@sitemap` |

The page and language routes use `SetWebsiteLocale`. Contact submission also uses
Laravel's `throttle:5,1`, CSRF protection, server-side validation, a honeypot field,
and the configured mail transport.

## Preserved system routes

- Existing ERP route `GET /services` remains named `services.index`.
- Existing `/login`, `/dashboard`, authenticated tenant routes, permission
  middleware, API routes, controllers, and response contracts were not renamed.
- The old root guest redirect was replaced only at `/`; login and authenticated
  dashboard behavior remain available through the marketing header.

## Why the public service URI differs

The requested source-site URI `/services` already belongs to the ERP Services module.
Laravel cannot expose two `GET /services` routes on the same host without shadowing
one of them. The public page therefore uses `/our-services`, preserving the current
ERP URL and behavior.

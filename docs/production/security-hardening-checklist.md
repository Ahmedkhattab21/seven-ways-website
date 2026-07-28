# Security Hardening Checklist

- [ ] PHP 8.2 and supported extensions; Laravel/vendor advisories reviewed.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL, stable backed-up APP_KEY.
- [ ] Proxy IPs explicit; HTTPS redirect tested without loops.
- [ ] Secure, HttpOnly, SameSite session cookies; login regenerates and logout invalidates.
- [ ] Login and public forms rate-limited; web writes retain CSRF protection.
- [ ] CSP contains no `unsafe-eval`; frame, MIME, referrer, and permissions headers present.
- [ ] 403/404/419/422/429/500/503 pages expose no stack, SQL, secrets, or paths.
- [ ] Correlation ID returned and logged; audit data masks secrets/account identifiers.
- [ ] Private attachments stay on `local`, use random names and allowlisted MIME/extensions.
- [ ] SVG/executable uploads rejected; downloads authorized, audited, and `nosniff`.
- [ ] Public storage contains public assets only; document root is `public/`.
- [ ] Company/branch policies, direct URLs, exports, approvals, and sensitive audit routes tested.
- [ ] CORS has explicit origins; no broad CSRF exceptions.
- [ ] Daily logs and retention configured outside public; log access restricted.
- [ ] `/health` and `/health/ready` rate-limited and return status labels only.
- [ ] Production seed creates references only; QA/demo seeders fail closed.
- [ ] Backups encrypted/private and restore drill evidenced.
- [ ] GitHub deployment is manual, dry-run first, least privilege, and environment-approved.


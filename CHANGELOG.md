# Changelog

All notable changes to `dashed-laposta` will be documented in this file.

## v4.0.12 - 2026-05-02

### Fixed
- `PopupAPI` herkent nu Laposta's 429 rate-limit response en gooit een `NewsletterRateLimitException` (uit dashed-popups) met geparseerde `retryAfter`-seconds. De queue-job in dashed-popups vangt die op en doet `release($delay)` zodat de andere submissions in dezelfde backfill-run niet ook crashen. Vereist `dashed-popups` v4.9.3+.

## v4.0.11 - 2026-05-02

### Added
- `Classes\PopupApis\PopupAPI`: nieuwe provider-class voor `dashed-popups` newsletter-sync. `dispatch(PopupView, array)` doet POST naar Laposta `/member` met `email`, `list_id`, `ip_address`, `source_url` en optionele custom-fields. "Email address exists" wordt netjes geslikt zodat dubbele submits geen exception veroorzaken; andere fouten gooien een `RuntimeException` met body-context.
- Registratie in `popupApiClasses` builder onder key `'laposta-popup-api'`. Vereist `dashed-forms` v4.0.22+.

## 1.0.0 - 202X-XX-XX

- initial release

# Changelog

All notable changes to `dashed-laposta` will be documented in this file.

## v4.0.14 - 2026-05-05

### Added
- `Classes\FormApis\OrderAPI` implementeert nu ook `Dashed\DashedEcommerceCore\Contracts\SupportsEmailBackfill`. Reden: de meeste Laposta-installaties hebben alleen `OrderAPI` in de Order-APIs Repeater geconfigureerd (de koppeling die bij elke betaalde order afgaat), niet de `NewsletterAPI`. Zonder backfill-support op `OrderAPI` deed de "Bestaande e-mails synchroniseren"-knop op de OrderSettingsPage niets, alle (email, api)-combinaties werden geskipped. `OrderAPI::syncEmail()` is identiek aan `NewsletterAPI::syncEmail()`: zelfde `list_id`-veld + zelfde Laposta `member`-endpoint.

## v4.0.13 - 2026-05-05

### Added
- `Classes\FormApis\NewsletterAPI` implementeert nu `Dashed\DashedEcommerceCore\Contracts\SupportsEmailBackfill`. Nieuwe `syncEmail(string $email, ?string $firstName, ?string $lastName, array $api): array` voegt een (email, voornaam, achternaam) toe aan de geconfigureerde Laposta lijst (`list_id` uit het API-config-blok). Gebruikt dezelfde `laposta_api_key` + `laposta_connected` Customsettings als de bestaande `dispatch`-paden, zodat de nieuwe `OrderSettingsPage` "Bestaande e-mails synchroniseren"-actie in dashed-ecommerce-core v4.9.0 kan backfillen. "Email address exists" wordt geslikt als success. Vereist dashed-ecommerce-core v4.9.0+.

## v4.0.12 - 2026-05-02

### Fixed
- `PopupAPI` herkent nu Laposta's 429 rate-limit response en gooit een `NewsletterRateLimitException` (uit dashed-popups) met geparseerde `retryAfter`-seconds. De queue-job in dashed-popups vangt die op en doet `release($delay)` zodat de andere submissions in dezelfde backfill-run niet ook crashen. Vereist `dashed-popups` v4.9.3+.

## v4.0.11 - 2026-05-02

### Added
- `Classes\PopupApis\PopupAPI`: nieuwe provider-class voor `dashed-popups` newsletter-sync. `dispatch(PopupView, array)` doet POST naar Laposta `/member` met `email`, `list_id`, `ip_address`, `source_url` en optionele custom-fields. "Email address exists" wordt netjes geslikt zodat dubbele submits geen exception veroorzaken; andere fouten gooien een `RuntimeException` met body-context.
- Registratie in `popupApiClasses` builder onder key `'laposta-popup-api'`. Vereist `dashed-forms` v4.0.22+.

## 1.0.0 - 202X-XX-XX

- initial release

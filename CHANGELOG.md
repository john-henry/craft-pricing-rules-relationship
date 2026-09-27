# Release Notes for Pricing Rules Relationship

## 1.1.0 - 2026-09-25

### Added
- Rules scheduled to start later can be ticked ahead of time, and show their start date.
- `craft.pricingRulesRelationship.getProductIds()`, the same as `getSaleIds()` but it also accepts an empty field.
- The field can be queried and saved over GraphQL, as a list of rule IDs.
- The products a set of rules covers can be queried over GraphQL with `pricingRuleProducts` and `pricingRuleProductCount`.
- A notice when the store still uses legacy Sales, since Commerce then ignores catalog pricing rules.
- The field works as an element query param, so `entries.salesRelationship(12)` finds entries using rule 12.

### Changed
- Saving an entry keeps expired and switched-off rules instead of dropping them, so they come back if the rule does.
- Only rules deleted from Commerce are flagged, and they're removed on save.
- Editors without Commerce's Manage promotions permission are only offered rules open to every customer.
- `getSaleIds()` only returns live products from the current store, and `hasStock` checks stock in that store.
- Cached pages built with `getSaleIds()` expire when one of their rules starts or ends.

### Fixed
- Unticking every rule now clears the field.
- A pricing rule with a broken condition no longer hides every other rule; only that one is left out.
- `getSaleIds()` is much quicker on large catalogues, and repeat calls in one request cost nothing.
- The "New Catalog Pricing Rule" button shows for editors who can create rules, not only admins.
- Posted rule IDs are checked against the rules on offer, and a field holds at most 100.

## 1.0.4 - 2026-09-21

### Fixed
- A pricing rule with a broken condition no longer stops the page rendering.

## 1.0.3 - 2026-07-05 [CRITICAL]

### Security
- Products from a rule scoped to a customer group are no longer shown to visitors outside that group.

### Changed
- `getSaleIds()` uses less memory on larger stores.

### Fixed
- The field returns an array of rule IDs instead of a JSON string.
- Expiry dates are compared in the right time zone.
- A malformed rule condition no longer causes a fatal error on the front end.
- The "New Catalog Pricing Rule" button is hidden when there's no store to link to.
- Disabled rules no longer show in the field or return products.
- Rules that haven't started yet don't return products until they start.
- `getSaleIds()` honours a rule's product conditions.
- `getSaleIds()` returns a clean list with no gaps or duplicates.

## 1.0.2 - 2026-06-04

### Added
- A Plugin Store icon.
- Translation support.

### Fixed
- An error when applying project config that includes the field.

## 1.0.1 - 2026-02-24

### Fixed
- Changelog dates.

## 1.0.0 - 2026-02-22

### Added
- Initial release.

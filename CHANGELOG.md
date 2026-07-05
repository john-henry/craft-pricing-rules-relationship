# Release Notes for Pricing Rules Relationship

## 1.0.3 [CRITICAL] - 2026-07-05

### Changed
- `getSaleIds()` no longer loads your whole catalog into memory to work out matches. It leans on Commerce's own product lookup instead, so it holds up better on larger stores.

### Fixed
- Fixed the `Pricing Rules Relationship` field returning its stored value as a raw JSON string instead of an array of rule IDs, which broke the documented `craft.pricingRulesRelationship.getSaleIds()` template pattern and the field's checkbox state
- Fixed a cross-customer-group data leak where products matched by a catalog pricing rule scoped to a customer group were returned to every visitor regardless of the current user
- Fixed a timezone mismatch when filtering expired pricing rules, so server-local time is no longer compared against UTC-stored expiry dates
- Fixed malformed pricing rule conditions potentially raising an uncaught fatal on the front end instead of degrading gracefully
- Fixed the "New Catalog Pricing Rule" button linking to a guessed store handle when no store is available, which could 404
- Disabled pricing rules no longer show in the field or hand their products back through `getSaleIds()`. Switch a rule off in Commerce and it stays off here too.
- Rules with a start date still in the future are held back until that date comes around, the same way expired rules already drop off once they're finished.
- `getSaleIds()` now honours a rule's full set of conditions, including product conditions, so it lines up with how Commerce itself decides which products a rule covers.
- Tidied up the product IDs coming back from `getSaleIds()` so you always get a clean, gap-free list.

## 1.0.2 - 2026-06-04

### Fixed
- Fixed a `Pricing Rules Relationship` field error that could occur when applying project config

### Added
- Added a proper icon for the Plugin Store listing
- Added translation support

### Changed
- Filled out the plugin's metadata, moved to an MIT licence, and confirmed support for PHP 8.3/8.4
- Fixed the changelog link pointing to the wrong branch

## 1.0.1 - 2026-02-24

### Changed
- Tidied up unused files
- Corrected changelog dates

## 1.0.0 - 2026-02-22

### Added
- Initial release
- `PricingRulesRelationshipField` for relating elements to Craft Commerce catalog pricing rules
- Service methods for retrieving applicable pricing rules for a given element
- `craft.pricingRulesRelationship` Twig variable

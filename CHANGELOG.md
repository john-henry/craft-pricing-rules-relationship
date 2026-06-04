# Release Notes for Pricing Rules Relationship

## 1.0.2 - 2026-06-04

### Fixed
- Restored missing `$defaultText` property on `PricingRulesRelationshipField` to prevent `UnknownPropertyException` when applying project config

### Added
- Added `config.php` for file-based configuration support
- Added icon mask for Plugin Store branding
- Added translation support

### Changed
- Updated `composer.json` with full plugin metadata, MIT licence, and PHP 8.3/8.4 support
- Fixed `changelogUrl` pointing to wrong branch

## 1.0.1 - 2026-02-24

### Changed
- Removed unused files
- Corrected changelog dates

## 1.0.0 - 2026-02-22

### Added
- Initial release
- `PricingRulesRelationshipField` for relating elements to Craft Commerce catalog pricing rules
- Service methods for retrieving applicable pricing rules for a given element
- `craft.pricingRulesRelationship` Twig variable

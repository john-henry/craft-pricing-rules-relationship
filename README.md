[![Stable Version](https://img.shields.io/packagist/v/johnhenry/craft-pricing-rules-relationship?label=stable&style=for-the-badge)](https://packagist.org/packages/johnhenry/craft-pricing-rules-relationship)
[![Static Badge](https://img.shields.io/badge/free-plugin?style=for-the-badge&logo=craftcms&logoColor=white&logoSize=auto&label=Craft%20Plugin%20Store&labelColor=%23E5422B)](https://plugins.craftcms.com/pricing-rules-relationship?craft5)

![Pricing Rules Relationship for Craft Commerce](https://johnhenry.ie/images/plugins/promos/pricing-rules-relationship/1.png)

# Pricing Rules Relationship for Craft Commerce

A field that ties Craft Commerce catalog pricing rules to any element. Tick the rules on an entry and your template gets the products those rules cover, so a Special Offers page fills itself and keeps itself right as rules start and expire.

## Features

- **Relate rules to anything**: Link Commerce catalog pricing rules to entries, products, categories, or any custom element type
- **Plain checkbox field**: Editors tick the rules they want from a straightforward list, no fuss
- **Expiry-aware**: Expired rules drop out of your templates on their own, so nobody has to untick anything by hand. You can show each rule's expiry date beside it too
- **Respects customer groups**: A rule scoped to a customer group only returns its products to users in that group, so nothing leaks across groups
- **In-stock filtering**: Pass `hasStock: true` and out-of-stock products are left off the list
- **Quick rule creation**: A "New Catalog Pricing Rule" button drops editors straight into Commerce to add one
- **Template-ready**: `craft.pricingRulesRelationship.getSaleIds()` hands you the matching product IDs, ready for a product query

## Perfect For

- Tying pricing rules to specific content
- Linking catalog pricing rules to marketing campaigns
- Keeping your pricing strategies organised across the commerce site

## Documentation

Full documentation is at [johnhenry.ie/plugins/pricing-rules-relationship/docs](https://johnhenry.ie/plugins/pricing-rules-relationship/docs/getting-started/overview).

## Requirements

- Craft CMS 5.0 or later
- Craft Commerce 5.0 or later
- PHP 8.2 or later

## Accessibility

How accessible the plugin is, what's been checked, and how to report a problem are all in the [accessibility statement](https://github.com/john-henry/craft-pricing-rules-relationship/blob/craft-5/ACCESSIBILITY.md).

## Support

Need a hand? Open an issue on the [GitHub Issues page](https://github.com/john-henry/craft-pricing-rules-relationship/issues).

## License

This plugin is free to use under the MIT License.

---

<a href="https://johnhenry.ie/plugins/" target="_blank">
    <img height="46" src="https://johnhenry.ie/images/plugins/logo.svg" alt="John Henry - Craft CMS Plugins">
</a>

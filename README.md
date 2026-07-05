[![Stable Version](https://img.shields.io/packagist/v/johnhenry/craft-pricing-rules-relationship?label=stable&style=for-the-badge)](https://packagist.org/packages/johnhenry/craft-pricing-rules-relationship)
[![Static Badge](https://img.shields.io/badge/free-plugin?style=for-the-badge&logo=craftcms&logoColor=white&logoSize=auto&label=Craft%20Plugin%20Store&labelColor=%23E5422B)](https://plugins.craftcms.com/pricing-rules-relationship?craft5)

<p align="center" style="margin-top:100px"><img width="120" height="120" alt="pricing-rules-plugin-icon" src="https://johnhenry.ie/images/plugins/craft-pricing-rules-relationship.svg"></p>

<h1 align="center">Pricing Rules Relationship for Craft Commerce</h1>


Craft Pricing Rules Relationship is a field that ties Craft Commerce catalog pricing rules to your elements. It fills the gap between Commerce's own pricing rule management and Craft's element relationships, so store managers can link a rule to any element type and then pull the matching products straight into their templates.

## Features
- **Relate rules to anything** – Link Commerce catalog pricing rules to entries, products, categories, or any custom element type
- **Plain checkbox field** – Editors tick the rules they want from a straightforward list, no fuss
- **Expiry-aware** – Expired rules drop out of your templates on their own, so nobody has to untick anything by hand. You can show each rule's expiry date beside it too
- **Respects customer groups** – A rule scoped to a customer group only returns its products to users in that group, so nothing leaks across groups
- **In-stock filtering** – Pass `hasStock: true` and out-of-stock products are left off the list
- **Quick rule creation** – A "New Catalog Pricing Rule" button drops editors straight into Commerce to add one
- **Template-ready** – `craft.pricingRulesRelationship.getSaleIds()` hands you the matching product IDs, ready for a product query

## Perfect For
- Tying pricing rules to specific content
- Linking catalog pricing rules to marketing campaigns
- Keeping your pricing strategies organised across the commerce site

## Documentation
Full Documentation can be found at [https://johnhenry.ie/plugins/pricing-rules-relationship/](https://johnhenry.ie/plugins/pricing-rules-relationship/)

## Support
For support, please visit our [GitHub Issues page](https://github.com/john-henry/craft-pricing-rules-relationship/issues)

## License

This package is licensed for free under the MIT License.

## Requirements

- Craft CMS 5.0 or later
- Craft Commerce 5.0 or later
- PHP 8.2 or later.

---

<a href="https://johnhenry.ie/plugins/" target="_blank">
    <img height="46" src="https://johnhenry.ie/images/plugins/logo.svg" alt="John Henry - Craft CMS Plugins">
</a>

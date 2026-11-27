[![Stable Version](https://img.shields.io/packagist/v/johnhenry/craft-pricing-rules-relationship?label=stable&style=for-the-badge)](https://packagist.org/packages/johnhenry/craft-pricing-rules-relationship)
[![Static Badge](https://img.shields.io/badge/free-plugin?style=for-the-badge&logo=craftcms&logoColor=white&logoSize=auto&label=Craft%20Plugin%20Store&labelColor=%23E5422B)](https://plugins.craftcms.com/pricing-rules-relationship?craft5)

![Pricing Rules Relationship for Craft Commerce](https://johnhenry.ie/images/plugins/promos/pricing-rules-relationship/1.png)

# Pricing Rules Relationship for Craft Commerce

Pricing Rules Relationship adds a field that links Craft Commerce catalog pricing rules to an entry,
or to any other element. Tick the rules that apply and your template gets the products they cover.
When a rule starts or ends, the page changes with it, so nobody has to go back and fix it by hand.

## Features

- **Any element:** put the field on entries, products, categories or your own element types.
- **A checkbox list for editors:** they tick the rules that apply. Each rule can show its end date,
  and one that hasn't started yet shows its start date, so a sale can be set up ahead of time.
- **Rules come and go on their own:** expired, switched-off and not-yet-started rules give no
  products, so nobody has to untick anything. A rule that's switched back on or extended comes back
  by itself.
- **Customer groups:** a rule limited to a customer group only returns its products to customers in
  that group.
- **Stock:** pass `true` as the second argument to `getProductIds()` and out-of-stock products are left out.
- **New rule button:** editors who can create pricing rules get a button that opens a new rule in
  Commerce, in a new tab.
- **Twig, element queries and GraphQL:** `craft.pricingRulesRelationship.getProductIds()` gives you
  the product IDs for a product query, the field works as an element query param, and
  `pricingRuleProducts` does the same job for a headless front end.

## Documentation

The full documentation is at [johnhenry.ie/plugins/pricing-rules-relationship/docs](https://johnhenry.ie/plugins/pricing-rules-relationship/docs/getting-started/overview).

## Requirements

- Craft CMS 5.0 or later
- Craft Commerce 5.0 or later
- PHP 8.2 or later

## Accessibility

How accessible the plugin is, what's been checked and how to report a problem are all in the [accessibility statement](https://github.com/john-henry/craft-pricing-rules-relationship/blob/craft-5/ACCESSIBILITY.md).

## Support

If you need a hand, open an issue on the [GitHub Issues page](https://github.com/john-henry/craft-pricing-rules-relationship/issues).

## License

This plugin is free to use under the MIT License.

---

<a href="https://johnhenry.ie/plugins/" target="_blank">
    <img height="46" src="https://johnhenry.ie/images/plugins/logo.svg" alt="John Henry - Craft CMS Plugins">
</a>

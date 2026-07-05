# Testing

## Running Tests

Pest lives in the parent Craft project's `vendor/`; the plugin does not have its own `vendor/` directory. All test commands run from the project root.

### Unit tests

No Craft application is bootstrapped.

```shell
ddev exec vendor/bin/pest plugins/craft-pricing-rules-relationship/tests/Unit \
  --test-directory=plugins/craft-pricing-rules-relationship/tests --colors
```

### Integration tests (require Craft + Commerce)

These live in `tests/Integration/` and use craft-pest's `TestCase` + `RefreshesDatabase`, which wraps each test in a DB transaction that rolls back on teardown, preventing test data from persisting to the database. They require `markhuot/craft-pest-core` in the parent project.

Both traits are applied globally in `Pest.php` via `uses(...)->in('Integration')`, so individual test files do not need their own `uses()` calls.

Run all tests (Unit and Integration):

```shell
ddev exec vendor/bin/pest plugins/craft-pricing-rules-relationship/tests \
  --test-directory=plugins/craft-pricing-rules-relationship/tests
```

Run only integration tests:

```shell
ddev exec vendor/bin/pest plugins/craft-pricing-rules-relationship/tests/Integration \
  --test-directory=plugins/craft-pricing-rules-relationship/tests/Integration
```

To filter to a specific describe block or test:

```shell
ddev exec vendor/bin/pest plugins/craft-pricing-rules-relationship/tests \
  --test-directory=plugins/craft-pricing-rules-relationship/tests --filter=expiry
```

## Test Structure

```
tests/
├── Pest.php                # Bootstrap: applies TestCase + RefreshesDatabase to Integration/
│                           # Defines insertPricingRule() helper
├── Unit/
│   └── PricingRulesRelationshipFieldTest.php  # displayName(), default properties, normalizeValue()
└── Integration/
    ├── PricingRulesRelationshipFieldTest.php   # save/reload round-trip, checked state, stale-rule warning
    └── PricingRulesRelationshipServiceTest.php # getMatchingProductsIds(): expiry, hasStock, customer conditions, malformed conditions
```

**Unit tests** cover the field's static metadata, default property values, and `normalizeValue()` decoding; no Craft instance needed.

**Integration tests** run against a real database and cover both the service and the field.

`PricingRulesRelationshipService::getMatchingProductsIds()`:

| Group | What is tested |
|---|---|
| No rules | Empty ID array and non-existent IDs return `[]` immediately |
| Expiry | Rules with a past `dateTo` are excluded; rules with `null` or a future `dateTo` are included |
| Enabled / start date | Disabled rules and rules with a future `dateFrom` are excluded; a past `dateFrom` is included |
| `hasStock` | Both `true` and `false` are accepted without error |
| Customer conditions | A group-scoped rule is left out for a non-member and for an anonymous visitor, and kept for a member |
| Malformed conditions | Invalid JSON in `variantCondition` / `purchasableCondition` does not throw |
| Return type | Always an array; no duplicate product IDs |

`PricingRulesRelationshipField`:

| Group | What is tested |
|---|---|
| Save/reload round-trip | Ticked rule IDs read back as a deduplicated `int[]`; an empty selection reads back as `[]` |
| Checked state | Re-rendering the input partial after reload ticks the right boxes |
| Stale-rule warning | The warning shows when a saved rule is gone and stays hidden when every saved rule still exists |

One helper is defined in `Pest.php`:

- `insertPricingRule(array $override = [])`: inserts a minimal rule row directly into `{{%commerce_catalogpricingrules}}`, resolving `storeId` from the first store in the database. Returns the inserted row ID.

### Coverage gap

Actual product matching runs through Commerce's `CatalogPricingRule::getPurchasableIds()`, which only returns anything when the store has real products and variants. The tests here assert the filtering rules (active/enabled/expiry/customer gating) and the return-type contract, but confirming that a given rule resolves to a given set of products needs Commerce products and variants in the test database, which the current suite does not set up.

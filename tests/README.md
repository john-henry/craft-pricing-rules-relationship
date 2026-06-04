# Testing

## Running Tests

Pest lives in the parent Craft project's `vendor/` — the plugin does not have its own `vendor/` directory. All test commands run from the project root.

### Unit tests

No Craft application is bootstrapped.

```shell
ddev exec vendor/bin/pest plugins/craft-pricing-rules-relationship/tests/Unit \
  --test-directory=plugins/craft-pricing-rules-relationship/tests --colors
```

### Integration tests (require Craft + Commerce)

These live in `tests/Integration/` and use craft-pest's `TestCase` + `RefreshesDatabase`, which wraps each test in a DB transaction that rolls back on teardown — preventing test data from persisting to the database. They require `markhuot/craft-pest-core` in the parent project.

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
│   └── PricingRulesRelationshipFieldTest.php  # displayName(), default properties
└── Integration/
    └── PricingRulesRelationshipServiceTest.php # getMatchingProductsIds() — expiry, hasStock, malformed conditions
```

**Unit tests** cover the field's static metadata and default property values — no Craft instance needed.

**Integration tests** cover `PricingRulesRelationshipService::getMatchingProductsIds()` against a real database:

| Group | What is tested |
|---|---|
| No rules | Empty ID array and non-existent IDs return `[]` immediately |
| Expiry | Rules with a past `dateTo` are excluded; rules with `null` or a future `dateTo` are included |
| `hasStock` | Both `true` and `false` are accepted without error |
| Malformed conditions | Invalid JSON in `variantCondition` / `purchasableCondition` does not throw |
| Return type | Always an array; no duplicate product IDs |

One helper is defined in `Pest.php`:

- `insertPricingRule(array $override = [])` — inserts a minimal rule row directly into `{{%commerce_catalogpricingrules}}`, resolving `storeId` from the first store in the database. Returns the inserted row ID.

### Coverage gap

`variantMatchesRule()` is a private method called only when both matching rules and Commerce variants exist in the database. The condition-matching path (valid condition JSON that accepts or rejects a variant) requires Commerce products/variants to be present in the test database and is not covered by the current suite.

<?php

/**
 * Unit tests run without a Craft application instance; any test calling
 * Craft::$app will fail in the Unit directory. For integration tests that
 * need a real Craft + Commerce context, install markhuot/craft-pest-core
 * in the parent Craft project and run:
 *
 *   vendor/bin/pest plugins/craft-pricing-rules-relationship/tests \
 *     --test-directory=plugins/craft-pricing-rules-relationship/tests
 */

use craft\commerce\Plugin as Commerce;
use craft\commerce\services\CatalogPricingRules;
use craft\db\Query;
use craft\helpers\StringHelper;
use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use johnhenry\pricingrulesrelationship\services\PricingRulesRelationshipService;
use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;

// Apply TestCase + RefreshesDatabase to every Integration test.
// RefreshesDatabase wraps each test in a DB transaction that rolls back on
// teardown, keeping the database clean between tests.
uses(
    TestCase::class,
    RefreshesDatabase::class,
)
    ->beforeEach(function() {
        // The service remembers results for the rest of a request, and the test
        // process is one long request.
        PricingRulesRelationship::getInstance()->set('pricingRulesRelationshipService', new PricingRulesRelationshipService());
    })
    ->in('Integration');

// ---------------------------------------------------------------------------
// Shared integration test helpers
// ---------------------------------------------------------------------------

/**
 * Insert a minimal catalog pricing rule directly into the DB and return its ID.
 * storeId is resolved from the first store in the database.
 *
 * Timestamps use gmdate() because Commerce stores dateFrom/dateTo/dateCreated
 * in UTC, and the service compares them against a UTC "now". Writing local-time
 * strings into these columns would misfire the expiry filter under a non-UTC
 * server timezone.
 */
function insertPricingRule(array $override = []): int
{
    $storeId = (int)(new Query())->select('id')->from('{{%commerce_stores}}')->scalar();

    $db = Craft::$app->getDb();
    $db->createCommand()->insert('{{%commerce_catalogpricingrules}}', array_merge([
        'name' => 'Test Rule',
        'storeId' => $storeId,
        'apply' => 'byPercent',
        'applyAmount' => -10.0000,
        'applyPriceType' => 'price',
        'enabled' => 1,
        'isPromotionalPrice' => 0,
        // Commerce's model setter takes string|array and fatals on null, so the
        // column's own nullability is not the contract to write against here.
        'metadata' => '[]',
        'variantCondition' => null,
        'purchasableCondition' => null,
        'customerCondition' => null,
        'dateFrom' => null,
        'dateTo' => null,
        'dateCreated' => gmdate('Y-m-d H:i:s'),
        'dateUpdated' => gmdate('Y-m-d H:i:s'),
        'uid' => StringHelper::UUID(),
    ], $override))->execute();

    $id = (int) $db->getLastInsertID();

    // Commerce memoises its rule collection per store, and only clears that memo
    // when a rule is saved through its own service. Rows written straight to the
    // table are invisible to getAllActiveCatalogPricingRules() for the rest of
    // the process, which makes every assertion built on them pass on an empty
    // collection. Clearing the memo is what gives these tests anything to match.
    clearPricingRuleMemo();

    return $id;
}

/**
 * Drop Commerce's memoised catalog pricing rule collection.
 */
function clearPricingRuleMemo(): void
{
    $service = Commerce::getInstance()->getCatalogPricingRules();

    Closure::bind(
        function() {
            $this->_clearCaches();
        },
        $service,
        CatalogPricingRules::class
    )();
}

/**
 * The IDs of live products in the current store owning at least one variant:
 * what a rule covering the whole catalogue should return. Built from product
 * and variant element queries rather than the service's own single-column read.
 *
 * @return int[]
 */
function expectedOwnerIds(): array
{
    $siteIds = Commerce::getInstance()->getStores()->getCurrentStore()->getSites()
        ->map(fn(craft\models\Site $site): int => (int)$site->id)
        ->all();

    $productIds = craft\commerce\elements\Product::find()->siteId($siteIds)->unique()->status('live')->ids();
    $owners = craft\commerce\elements\Variant::find()->siteId($siteIds)->unique()->status(null)->productId($productIds)->all();

    $ids = array_values(array_unique(array_map(static fn($variant): int => (int)$variant->primaryOwnerId, $owners)));
    sort($ids);

    return $ids;
}

/**
 * Sorts a result for comparison with expectedOwnerIds().
 *
 * @param int[] $ids
 * @return int[]
 */
function sortedIds(array $ids): array
{
    sort($ids);

    return $ids;
}

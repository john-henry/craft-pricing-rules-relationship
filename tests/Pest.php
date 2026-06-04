<?php

/**
 * Unit tests run without a Craft application instance — any test calling
 * Craft::$app will fail in the Unit directory. For integration tests that
 * need a real Craft + Commerce context, install markhuot/craft-pest-core
 * in the parent Craft project and run:
 *
 *   vendor/bin/pest plugins/craft-pricing-rules-relationship/tests \
 *     --test-directory=plugins/craft-pricing-rules-relationship/tests
 */

use craft\db\Query;
use craft\helpers\StringHelper;
use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;

// Apply TestCase + RefreshesDatabase to every Integration test.
// RefreshesDatabase wraps each test in a DB transaction that rolls back on
// teardown, keeping the database clean between tests.
uses(
    TestCase::class,
    RefreshesDatabase::class,
)->in('Integration');

// ---------------------------------------------------------------------------
// Shared integration test helpers
// ---------------------------------------------------------------------------

/**
 * Insert a minimal catalog pricing rule directly into the DB and return its ID.
 * storeId is resolved from the first store in the database.
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
        'variantCondition' => null,
        'purchasableCondition' => null,
        'customerCondition' => null,
        'dateFrom' => null,
        'dateTo' => null,
        'dateCreated' => date('Y-m-d H:i:s'),
        'dateUpdated' => date('Y-m-d H:i:s'),
        'uid' => StringHelper::UUID(),
    ], $override))->execute();

    return (int) $db->getLastInsertID();
}

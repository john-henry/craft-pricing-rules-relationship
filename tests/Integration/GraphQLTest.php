<?php

use craft\commerce\Plugin as Commerce;
use craft\helpers\StringHelper;
use craft\models\GqlSchema;
use johnhenry\pricingrulesrelationship\gql\queries\PricingRuleProducts;

// ---------------------------------------------------------------------------
// GraphQL: pricingRuleProducts
// ---------------------------------------------------------------------------
// The field returns rule IDs; these queries turn them into products the same
// way the Twig variable does, and hand them to Commerce's own product
// resolver, so the schema's product type permissions still decide what shows.

function productSchema(bool $withProducts = true): GqlSchema
{
    $scope = [];

    if ($withProducts) {
        foreach (Commerce::getInstance()->getProductTypes()->getAllProductTypes() as $type) {
            $scope[] = "productTypes.{$type->uid}:read";
        }
    }

    // Craft caches a built schema by UID, so each one needs its own.
    return new GqlSchema(['name' => 'Test', 'uid' => StringHelper::UUID(), 'scope' => $scope]);
}

function runGql(GqlSchema $schema, string $query, array $variables = []): array
{
    return Craft::$app->getGql()->executeQuery($schema, $query, $variables);
}

describe('pricingRuleProducts', function() {
    it('returns the products a rule covers', function() {
        $id = insertPricingRule(['dateTo' => null]);

        $result = runGql(productSchema(), 'query($ids: [Int]!) {
            pricingRuleProducts(ruleIds: $ids) { id title }
            pricingRuleProductCount(ruleIds: $ids)
        }', ['ids' => [$id]]);

        $ids = array_map('intval', array_column($result['data']['pricingRuleProducts'], 'id'));
        sort($ids);

        expect($result)->not->toHaveKey('errors')
            ->and($ids)->toEqual(expectedOwnerIds())->not->toBeEmpty()
            ->and($result['data']['pricingRuleProductCount'])->toBe(count($ids));
    });

    it('returns nothing for an expired rule', function() {
        $id = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))]);

        $result = runGql(productSchema(), 'query($ids: [Int]!) {
            pricingRuleProducts(ruleIds: $ids) { id }
            pricingRuleProductCount(ruleIds: $ids)
        }', ['ids' => [$id]]);

        expect($result['data']['pricingRuleProducts'])->toBe([])
            ->and($result['data']['pricingRuleProductCount'])->toBe(0);
    });

    // An id argument narrows the match; it must never add products the rules
    // don't cover.
    it('lets id narrow the result but not widen it', function() {
        $id = insertPricingRule(['dateTo' => null]);
        $covered = expectedOwnerIds();

        $narrowed = runGql(productSchema(), 'query($ids: [Int]!, $only: [QueryArgument]) {
            pricingRuleProducts(ruleIds: $ids, id: $only) { id }
        }', ['ids' => [$id], 'only' => [$covered[0], PHP_INT_MAX]]);

        expect(array_map('intval', array_column($narrowed['data']['pricingRuleProducts'], 'id')))->toBe([$covered[0]]);
    });

    // Craft builds the query list once per request, from that request's
    // schema, so the gate is checked against the active schema directly.
    it('is only offered to a schema that can read products', function() {
        $gql = Craft::$app->getGql();

        $gql->setActiveSchema(productSchema(false));
        $without = PricingRuleProducts::getQueries();

        $gql->setActiveSchema(productSchema());
        $with = PricingRuleProducts::getQueries();

        expect($without)->toBe([])
            ->and(array_keys($with))->toBe(['pricingRuleProducts', 'pricingRuleProductCount']);
    });
});

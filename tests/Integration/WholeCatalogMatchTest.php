<?php

use johnhenry\pricingrulesrelationship\PricingRulesRelationship;

// ---------------------------------------------------------------------------
// The rest of this suite asserts only toBeArray(), which holds whether or not
// the service resolves a single product. These pin the actual answer: a rule
// with no product, variant or purchasable conditions covers the whole
// catalogue, so every live product in the current store must come back.
//
// The expectation is built from the element query plus a separate owner-ID
// lookup, which is a different route to the same set than the service's own
// array-mode read, so a mis-keyed column comes back as a failure rather than a
// quietly empty result.
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): whole catalogue', function() {
    it('returns every product owning a variant for a rule with no conditions', function() {
        $expected = expectedOwnerIds();

        expect($expected)->not->toBeEmpty(
            'The test database has no Commerce variants, so this test proves nothing.'
        );

        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect(sortedIds($result))->toEqual($expected);
    });

    it('returns integers, not numeric strings', function() {
        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->not->toBeEmpty();

        foreach ($result as $productId) {
            expect($productId)->toBeInt();
        }
    });

    it('returns a list with sequential keys after de-duplication', function() {
        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect(array_keys($result))->toEqual(range(0, count($result) - 1));
    });
});

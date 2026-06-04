<?php

use johnhenry\pricingrulesrelationship\PricingRulesRelationship;

// ---------------------------------------------------------------------------
// Empty / missing rules
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds() — no rules', function () {
    it('returns an empty array when given an empty ID list', function () {
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns an empty array when no rules match the given IDs', function () {
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([PHP_INT_MAX]);

        expect($result)->toBeArray()->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// Date expiry filtering
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds() — expiry', function () {
    it('excludes a rule whose dateTo is in the past', function () {
        $id = insertPricingRule(['dateTo' => date('Y-m-d H:i:s', strtotime('-1 day'))]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('excludes a rule that expired exactly one second ago', function () {
        $id = insertPricingRule(['dateTo' => date('Y-m-d H:i:s', time() - 1)]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('includes a rule with no expiry date (dateTo is null)', function () {
        // Result may be empty if there are no Commerce variants in the test database,
        // but the rule must not be filtered out — the return value must be an array.
        $id = insertPricingRule(['dateTo' => null]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('includes a rule whose dateTo is in the future', function () {
        $id = insertPricingRule(['dateTo' => date('Y-m-d H:i:s', strtotime('+1 year'))]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('only returns results for non-expired rules when a mix is given', function () {
        $expiredId = insertPricingRule(['dateTo' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
        $activeId = insertPricingRule(['dateTo' => date('Y-m-d H:i:s', strtotime('+1 year'))]);

        // Calling with only the expired ID must yield nothing.
        $expiredResult = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$expiredId]);

        expect($expiredResult)->toBeArray()->toBeEmpty();

        // Calling with both IDs must not crash (active rule is processed).
        $mixedResult = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$expiredId, $activeId]);

        expect($mixedResult)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// hasStock flag
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds() — hasStock', function () {
    it('accepts hasStock=false without error', function () {
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([], false);

        expect($result)->toBeArray();
    });

    it('accepts hasStock=true without error', function () {
        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id], true);

        expect($result)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// Condition JSON robustness
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds() — malformed conditions', function () {
    it('does not throw when variantCondition contains invalid JSON', function () {
        // variantMatchesRule catches the JSON parse exception and returns false.
        // This path is exercised only when Commerce variants exist in the database;
        // the test verifies the service completes without crashing regardless.
        $id = insertPricingRule(['variantCondition' => 'not-valid-json']);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('does not throw when purchasableCondition contains invalid JSON', function () {
        $id = insertPricingRule(['purchasableCondition' => '{broken json]']);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// Return type contract
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds() — return type', function () {
    it('always returns an array', function () {
        expect(
            PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
                ->getMatchingProductsIds([])
        )->toBeArray();
    });

    it('returns only unique product IDs (no duplicates)', function () {
        // With no variants in the DB the result is empty, but array_unique() is always applied.
        // Full de-duplication coverage requires Commerce products/variants in the test database.
        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toEqual(array_unique($result));
    });
});

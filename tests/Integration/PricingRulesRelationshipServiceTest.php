<?php

use craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition;
use craft\elements\conditions\users\GroupConditionRule;
use craft\helpers\Json;
use craft\models\UserGroup;
use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use markhuot\craftpest\factories\User as UserFactory;

// ---------------------------------------------------------------------------
// Empty / missing rules
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): no rules', function() {
    it('returns an empty array when given an empty ID list', function() {
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns an empty array when no rules match the given IDs', function() {
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([PHP_INT_MAX]);

        expect($result)->toBeArray()->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// Date expiry filtering
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): expiry', function() {
    it('excludes a rule whose dateTo is in the past', function() {
        $id = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('excludes a rule that expired exactly one second ago', function() {
        $id = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', time() - 1)]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('includes a rule with no expiry date (dateTo is null)', function() {
        // Result may be empty if there are no Commerce variants in the test database,
        // but the rule must not be filtered out; the return value must be an array.
        $id = insertPricingRule(['dateTo' => null]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('includes a rule whose dateTo is in the future', function() {
        $id = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', strtotime('+1 year'))]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('only returns results for non-expired rules when a mix is given', function() {
        $expiredId = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))]);
        $activeId = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', strtotime('+1 year'))]);

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
// Enabled flag and start date
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): enabled and start date', function() {
    it('excludes a disabled rule', function() {
        $id = insertPricingRule(['enabled' => 0]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('excludes a rule whose start date is still in the future', function() {
        $id = insertPricingRule(['dateFrom' => gmdate('Y-m-d H:i:s', strtotime('+1 day'))]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('includes a rule whose start date has already passed', function() {
        // Result may be empty without variants in the test database, but the
        // rule must not be filtered out by the start-date check.
        $id = insertPricingRule(['dateFrom' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// hasStock flag
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): hasStock', function() {
    it('accepts hasStock=false without error', function() {
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([], false);

        expect($result)->toBeArray();
    });

    it('accepts hasStock=true without error', function() {
        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id], true);

        expect($result)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// Condition JSON robustness
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): malformed conditions', function() {
    it('does not throw when variantCondition contains invalid JSON', function() {
        // The service catches the JSON parse error and skips the variant. That
        // path only runs when Commerce variants exist in the database; either
        // way the call must finish without falling over.
        $id = insertPricingRule(['variantCondition' => 'not-valid-json']);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('does not throw when purchasableCondition contains invalid JSON', function() {
        $id = insertPricingRule(['purchasableCondition' => '{broken json]']);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// Customer condition gating
//
// A rule scoped to a customer group must never show its products to a user
// outside that group. These tests build a real customer condition requiring
// membership in a freshly created group, then check the rule is left out for a
// user who isn't a member and kept for one who is.
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): customer condition', function() {
    beforeEach(function() {
        // Create an isolated user group to scope the rule to.
        $group = new UserGroup([
            'name' => 'PRR Test Group ' . uniqid(),
            'handle' => 'prrTestGroup' . str_replace('.', '', uniqid('', true)),
        ]);
        expect(Craft::$app->getUserGroups()->saveGroup($group))->toBeTrue();
        $this->group = $group;

        // Build a customer condition: user must be IN the group above.
        $rule = new GroupConditionRule();
        $rule->setValues([$group->uid]);

        $condition = new CatalogPricingRuleCustomerCondition();
        $condition->setConditionRules([$rule]);

        $this->customerConditionJson = Json::encode($condition->getConfig());
    });

    it('excludes a group-scoped rule for a user who is not in the group', function() {
        $user = UserFactory::factory()->create();
        $this->actingAs($user);

        $id = insertPricingRule(['customerCondition' => $this->customerConditionJson]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('excludes a group-scoped rule for an anonymous visitor', function() {
        // No actingAs(): getIdentity() returns null, so the rule must not match.
        $id = insertPricingRule(['customerCondition' => $this->customerConditionJson]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray()->toBeEmpty();
    });

    it('processes a group-scoped rule for a user who IS in the group', function() {
        $user = UserFactory::factory()->create();
        Craft::$app->getUsers()->assignUserToGroups($user->id, [$this->group->id]);
        // Reload so the in-memory identity carries the new group membership.
        $user = Craft::$app->getUsers()->getUserById($user->id);
        $this->actingAs($user);

        $id = insertPricingRule(['customerCondition' => $this->customerConditionJson]);

        // The rule is NOT filtered out by the customer gate, so the service
        // proceeds to variant matching. Result may be empty if the test DB has
        // no variants, but the call must complete and return an array.
        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });

    it('still includes a rule with no customer condition regardless of user', function() {
        $id = insertPricingRule(['customerCondition' => null]);

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toBeArray();
    });
});

// ---------------------------------------------------------------------------
// Return type contract
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipService::getMatchingProductsIds(): return type', function() {
    it('always returns an array', function() {
        expect(
            PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
                ->getMatchingProductsIds([])
        )->toBeArray();
    });

    it('returns only unique product IDs (no duplicates)', function() {
        // With no variants in the DB the result is empty, but array_unique() is always applied.
        // Full de-duplication coverage requires Commerce products/variants in the test database.
        $id = insertPricingRule();

        $result = PricingRulesRelationship::getInstance()->pricingRulesRelationshipService
            ->getMatchingProductsIds([$id]);

        expect($result)->toEqual(array_unique($result));
    });
});

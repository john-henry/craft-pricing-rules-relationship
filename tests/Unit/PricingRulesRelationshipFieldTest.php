<?php

use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;

// ---------------------------------------------------------------------------
// Static metadata
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField::displayName()', function () {
    it('returns the correct display name', function () {
        expect(PricingRulesRelationshipField::displayName())->toBe('Pricing Rules Relationship');
    });
});

// ---------------------------------------------------------------------------
// Default property values
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField defaults', function () {
    it('shows the new-rule button by default', function () {
        $field = new PricingRulesRelationshipField();

        expect($field->showNewRuleButton)->toBeTrue();
    });

    it('shows rule expiry dates by default', function () {
        $field = new PricingRulesRelationshipField();

        expect($field->showRuleExpiryDates)->toBeTrue();
    });
});

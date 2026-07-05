<?php

use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;

// ---------------------------------------------------------------------------
// Static metadata
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField::displayName()', function() {
    it('returns the correct display name', function() {
        expect(PricingRulesRelationshipField::displayName())->toBe('Pricing Rules Relationship');
    });
});

// ---------------------------------------------------------------------------
// Default property values
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField defaults', function() {
    it('shows the new-rule button by default', function() {
        $field = new PricingRulesRelationshipField();

        expect($field->showNewRuleButton)->toBeTrue();
    });

    it('shows rule expiry dates by default', function() {
        $field = new PricingRulesRelationshipField();

        expect($field->showRuleExpiryDates)->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// normalizeValue(): decode the stored value into an int[]
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField::normalizeValue()', function() {
    it('decodes a JSON-encoded array of ID strings into an int array', function() {
        $field = new PricingRulesRelationshipField();

        $result = $field->normalizeValue('["3","27"]', null);

        expect($result)->toBe([3, 27]);
    });

    it('returns an int array unchanged when already an array of strings', function() {
        $field = new PricingRulesRelationshipField();

        $result = $field->normalizeValue(['3', '27'], null);

        expect($result)->toBe([3, 27]);
    });

    it('casts integer members to int and reindexes', function() {
        $field = new PricingRulesRelationshipField();

        $result = $field->normalizeValue([3, 27], null);

        expect($result)->toBe([3, 27]);
    });

    it('deduplicates repeated IDs', function() {
        $field = new PricingRulesRelationshipField();

        $result = $field->normalizeValue('["3","3","27"]', null);

        expect($result)->toBe([3, 27]);
    });

    it('drops non-numeric members', function() {
        $field = new PricingRulesRelationshipField();

        $result = $field->normalizeValue(['3', 'abc', '', '27'], null);

        expect($result)->toBe([3, 27]);
    });

    it('returns an empty array for null', function() {
        $field = new PricingRulesRelationshipField();

        expect($field->normalizeValue(null, null))->toBe([]);
    });

    it('returns an empty array for an empty JSON array', function() {
        $field = new PricingRulesRelationshipField();

        expect($field->normalizeValue('[]', null))->toBe([]);
    });

    it('returns an empty array for a non-JSON string', function() {
        $field = new PricingRulesRelationshipField();

        expect($field->normalizeValue('not json', null))->toBe([]);
    });
});

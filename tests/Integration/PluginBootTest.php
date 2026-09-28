<?php

/**
 * The plugin's wiring lives in a trait; this is the check that it is all still
 * attached to the class that uses it.
 */

use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;
use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use johnhenry\pricingrulesrelationship\services\PricingRulesRelationshipService;
use johnhenry\pricingrulesrelationship\variables\PricingRulesRelationshipVariable;

describe('Plugin wiring', function() {
    it('registers the field type', function() {
        expect(Craft::$app->getFields()->getAllFieldTypes())
            ->toContain(PricingRulesRelationshipField::class);
    });

    it('exposes the twig variable', function() {
        $craft = Craft::$app->getView()->getTwig()->getGlobals()['craft'];

        expect($craft->pricingRulesRelationship)
            ->toBeInstanceOf(PricingRulesRelationshipVariable::class);
    });

    it('resolves the service both ways', function() {
        $plugin = PricingRulesRelationship::getInstance();

        expect($plugin->getPricingRulesRelationshipService())
            ->toBeInstanceOf(PricingRulesRelationshipService::class)
            ->and($plugin->pricingRulesRelationshipService)
            ->toBeInstanceOf(PricingRulesRelationshipService::class);
    });
});

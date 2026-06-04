<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\variables;

use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use yii\base\InvalidConfigException;

/**
 * Exposes pricing rules relationship methods to Twig via `craft.pricingRulesRelationship`.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationshipVariable
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * Returns the primary-owner IDs of products matching the given pricing rules.
     *
     * @param array $selectedSaleIds Catalog pricing rule IDs to match against.
     * @param bool $hasStock Whether to restrict results to in-stock variants.
     * @return array<int>
     * @throws InvalidConfigException
     */
    public function getSaleIds(array $selectedSaleIds, bool $hasStock = false): array
    {
        return PricingRulesRelationship::getInstance()->pricingRulesRelationshipService->getMatchingProductsIds($selectedSaleIds, $hasStock);
    }
}

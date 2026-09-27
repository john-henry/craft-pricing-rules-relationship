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
     * Returns the IDs of products in the current store matching the given
     * pricing rules.
     *
     * @param mixed $selectedSaleIds Catalog pricing rule IDs, usually the field value. Anything
     *                               that isn't a list counts as none.
     * @param bool $hasStock Whether to restrict results to in-stock variants.
     * @return array<int> The product IDs.
     * @throws InvalidConfigException If the service can't be resolved.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public function getProductIds(mixed $selectedSaleIds, bool $hasStock = false): array
    {
        return PricingRulesRelationship::getInstance()
            ->getPricingRulesRelationshipService()
            ->getMatchingProductsIds(is_array($selectedSaleIds) ? $selectedSaleIds : [], $hasStock);
    }

    /**
     * Returns the IDs of products in the current store matching the given
     * pricing rules. Kept for existing templates; `getProductIds()` is the same.
     *
     * @param mixed $selectedSaleIds Catalog pricing rule IDs, usually the field value.
     * @param bool $hasStock Whether to restrict results to in-stock variants.
     * @return array<int> The product IDs.
     * @throws InvalidConfigException If the service can't be resolved.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    public function getSaleIds(mixed $selectedSaleIds, bool $hasStock = false): array
    {
        return $this->getProductIds($selectedSaleIds, $hasStock);
    }
}

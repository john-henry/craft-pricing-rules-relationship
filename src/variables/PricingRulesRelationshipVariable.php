<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\variables;

use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use yii\base\InvalidConfigException;

class PricingRulesRelationshipVariable
{
    /**
     * @throws InvalidConfigException
     */
    public function getSaleIds($selectedSaleIds, $hasStock = false): array
    {
        return PricingRulesRelationship::getInstance()->pricingRulesRelationshipService->getMatchingProductsIds($selectedSaleIds, $hasStock);
    }
}

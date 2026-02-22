<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\salesrelationship\variables;

use johnhenry\salesrelationship\SalesRelationship;

class SalesRelationshipVariable
{
    public function getSaleIds($selectedSaleIds, $hasStock = false): array
    {
        return SalesRelationship::getInstance()->salesRelationshipService->getMatchingProductsIds($selectedSaleIds, $hasStock);
    }
}

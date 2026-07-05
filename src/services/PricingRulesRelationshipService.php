<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;
use yii\base\InvalidConfigException;

/**
 * Resolves which products match a set of Commerce catalog pricing rules.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationshipService extends Component
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * Returns the primary-owner IDs of products whose variants match any of the
     * given catalog pricing rules.
     *
     * Only rules that are currently active (enabled and inside their date
     * window) and eligible for the current user are counted. Working out which
     * variants a rule actually covers is handed to Commerce's own
     * CatalogPricingRule::getPurchasableIds(), so we never pull the whole
     * variant table into memory.
     *
     * @param array $selectedSaleIds Catalog pricing rule IDs to match against.
     * @param bool $hasStock Whether to restrict results to in-stock variants.
     * @return array<int> Deduplicated product IDs.
     * @throws InvalidConfigException
     */
    public function getMatchingProductsIds(array $selectedSaleIds, bool $hasStock = false): array
    {
        $selectedSaleIds = array_map('intval', $selectedSaleIds);

        if (empty($selectedSaleIds)) {
            return [];
        }

        // Active = enabled and inside its date window: Commerce's own idea of an
        // active rule. Narrow that to the rules that were actually selected and
        // that the current user is allowed to see.
        /** @var CatalogPricingRule[] $rules */
        $rules = Commerce::getInstance()->getCatalogPricingRules()
            ->getAllActiveCatalogPricingRules()
            ->whereIn('id', $selectedSaleIds)
            ->filter(fn(CatalogPricingRule $rule): bool => $this->_currentUserMatchesRule($rule))
            ->all();

        if (empty($rules)) {
            return [];
        }

        $variantIds = [];
        $matchesWholeCatalog = false;

        foreach ($rules as $rule) {
            try {
                $ruleVariantIds = $rule->getPurchasableIds();
            } catch (\Throwable $e) {
                // A rule we can't resolve (say, a malformed condition) is left
                // out rather than risking a fatal on the front end.
                Craft::warning(
                    "Failed to resolve products for catalog pricing rule {$rule->id}: {$e->getMessage()}",
                    'pricing-rules-relationship',
                );

                continue;
            }

            // A null purchasable list means the rule carries no product, variant
            // or purchasable conditions, so it covers the whole catalog and
            // there's no ID list to gather.
            if ($ruleVariantIds === null) {
                $matchesWholeCatalog = true;
                break;
            }

            if (!empty($ruleVariantIds)) {
                array_push($variantIds, ...$ruleVariantIds);
            }
        }

        if (!$matchesWholeCatalog && empty($variantIds)) {
            return [];
        }

        $variantQuery = Variant::find()->site('*');

        if (!$matchesWholeCatalog) {
            $variantQuery->id(array_values(array_unique($variantIds)));
        }

        if ($hasStock) {
            $variantQuery->hasStock();
        }

        $matchingProductIds = [];

        foreach ($variantQuery->all() as $variant) {
            $ownerId = $variant->getPrimaryOwnerId();

            if ($ownerId !== null) {
                $matchingProductIds[] = $ownerId;
            }
        }

        return array_values(array_unique($matchingProductIds));
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Returns whether the current user satisfies a rule's customer condition.
     *
     * A rule with no customer condition applies to everyone. A rule scoped to a
     * customer group only applies when the current user matches that condition;
     * an anonymous visitor never matches a group-scoped rule. If the condition
     * can't be evaluated the user counts as "not eligible", so bad data never
     * shows a rule's products to the wrong people.
     *
     * @param CatalogPricingRule $rule The rule to test.
     * @return bool
     */
    private function _currentUserMatchesRule(CatalogPricingRule $rule): bool
    {
        try {
            $condition = $rule->getCustomerCondition();

            if (empty($condition->getConditionRules())) {
                return true;
            }

            $user = Craft::$app->getUser()->getIdentity();

            if (!$user instanceof User) {
                return false;
            }

            return $condition->matchElement($user);
        } catch (\Throwable $e) {
            Craft::warning(
                "Failed to evaluate catalog pricing rule customer condition: {$e->getMessage()}",
                'pricing-rules-relationship',
            );

            return false;
        }
    }
}

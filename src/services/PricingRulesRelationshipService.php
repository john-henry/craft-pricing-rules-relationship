<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\commerce\elements\Variant;
use craft\db\Query;
use DateTime;
use Exception;
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
     * @param array $selectedSaleIds Catalog pricing rule IDs to match against.
     * @param bool $hasStock Whether to restrict results to in-stock variants.
     * @return array<int> Deduplicated product IDs.
     * @throws InvalidConfigException
     */
    public function getMatchingProductsIds(array $selectedSaleIds, bool $hasStock = false): array
    {
        $now = new DateTime();
        $catalogPricingRules = (new Query())
            ->select(['id', 'variantCondition', 'purchasableCondition'])
            ->from(['{{%commerce_catalogpricingrules}}'])
            ->where(['id' => $selectedSaleIds])
            ->andWhere([
                'or',
                ['dateTo' => null],
                ['>', 'dateTo', $now->format('Y-m-d H:i:s')],
            ])
            ->all();

        if (empty($catalogPricingRules)) {
            return [];
        }

        $variantQuery = Variant::find()->site('*');

        if ($hasStock) {
            $variantQuery->hasStock();
        }

        $allVariants = $variantQuery->all();

        $matchingProductIds = [];

        foreach ($allVariants as $variant) {
            foreach ($catalogPricingRules as $rule) {
                if ($this->_variantMatchesRule($variant, $rule)) {
                    $matchingProductIds[] = $variant->getPrimaryOwnerId();
                    break;
                }
            }
        }

        return array_unique($matchingProductIds);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Returns whether a variant satisfies the conditions of a pricing rule row.
     *
     * @param ElementInterface $variant The variant to test.
     * @param array $rule Raw rule row from the DB (keys: variantCondition, purchasableCondition).
     * @return bool
     */
    private function _variantMatchesRule(ElementInterface $variant, array $rule): bool
    {
        if (empty($rule['variantCondition']) && empty($rule['purchasableCondition'])) {
            return true;
        }

        try {
            if (!empty($rule['variantCondition'])) {
                $conditionConfig = json_decode($rule['variantCondition'], true, 512, JSON_THROW_ON_ERROR);
                $condition = Craft::$app->getConditions()->createCondition($conditionConfig);
                if (method_exists($condition, 'matchElement') && !$condition->matchElement($variant)) {
                    return false;
                }
            }

            if (!empty($rule['purchasableCondition'])) {
                $conditionConfig = json_decode($rule['purchasableCondition'], true, 512, JSON_THROW_ON_ERROR);
                $condition = Craft::$app->getConditions()->createCondition($conditionConfig);
                if (method_exists($condition, 'matchElement') && !$condition->matchElement($variant)) {
                    return false;
                }
            }

            return true;
        } catch (Exception) {
            return false;
        }
    }
}

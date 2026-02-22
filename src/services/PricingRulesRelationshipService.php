<?php

declare(strict_types=1);

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

class PricingRulesRelationshipService extends Component
{
    /**
     * @throws InvalidConfigException
     */
    public function getMatchingProductsIds($selectedSaleIds, $hasStock = false): array
    {
        // Get the catalog pricing rules (exclude expired ones)
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

        // Get all variants
        $variantQuery = Variant::find();

        if ($hasStock) {
            $variantQuery->hasStock();
        }

        $allVariants = $variantQuery->all();

        $matchingProductIds = [];

        foreach ($allVariants as $variant) {
            // Check if variant matches any of the selected catalog pricing rules
            foreach ($catalogPricingRules as $rule) {
                if ($this->variantMatchesRule($variant, $rule)) {
                    $matchingProductIds[] = $variant->getPrimaryOwnerId();
                    break; // Move to next variant once matched
                }
            }
        }
        // Remove duplicates and return
        return array_unique($matchingProductIds);
    }

    private function variantMatchesRule(ElementInterface $variant, array $rule): bool
    {
        // If no conditions specified, all variants with promotional pricing match
        if (empty($rule['variantCondition']) && empty($rule['purchasableCondition'])) {
            return true;
        }

        try {
            // Check variant condition
            if (!empty($rule['variantCondition'])) {
                $conditionConfig = json_decode($rule['variantCondition'], true, 512, JSON_THROW_ON_ERROR);
                $condition = Craft::$app->getConditions()->createCondition($conditionConfig);
                if (method_exists($condition, 'matchElement') && !$condition->matchElement($variant)) {
                    return false;
                }
            }

            // Check purchasable condition
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

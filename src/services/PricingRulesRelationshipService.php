<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\services;

use Craft;
use craft\base\Component;
use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Variant;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\Store;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\elements\User;
use craft\models\Site;
use DateTime;
use Throwable;
use yii\base\InvalidConfigException;

/**
 * Resolves which products match a set of Commerce catalog pricing rules.
 *
 * Results depend on the current store and the current user, since rules can be
 * limited to customer groups or single customers. Anything that caches a page
 * built from them has to vary by user too.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationshipService extends Component
{
    // =========================================================================
    // Private Properties
    // =========================================================================

    /**
     * @var array<string, int[]> Results already worked out in this request, keyed
     * by store, user, stock flag and rule IDs.
     */
    private array $_memo = [];

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * Returns the IDs of products in the current store whose variants match any
     * of the given catalog pricing rules.
     *
     * Only rules that are currently active (enabled and inside their date
     * window) and open to the current user are counted. Which variants a rule
     * covers is handed to Commerce's own CatalogPricingRule::getPurchasableIds().
     *
     * Product IDs are read as a single column, which skips the per-row work
     * Commerce's purchasable query does when it builds full rows; on a rule that
     * covers the whole catalogue that work grows with the square of the variant
     * count.
     *
     * @param array<int|string> $selectedSaleIds Catalog pricing rule IDs to match against.
     * @param bool $hasStock Whether to restrict results to variants in stock in the current store.
     * @return array<int> Deduplicated product IDs.
     * @throws InvalidConfigException If Commerce cannot resolve its services.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    public function getMatchingProductsIds(array $selectedSaleIds, bool $hasStock = false): array
    {
        $selectedSaleIds = array_values(array_unique(array_map(
            static fn(mixed $id): int => (int)$id,
            $selectedSaleIds,
        )));

        if ($selectedSaleIds === []) {
            return [];
        }

        $store = Commerce::getInstance()->getStores()->getCurrentStore();
        $user = Craft::$app->getUser()->getIdentity();

        $sorted = $selectedSaleIds;
        sort($sorted);
        $key = implode(':', [$store->id, $user->id ?? 'guest', (int)$hasStock, implode(',', $sorted)]);

        return $this->_memo[$key] ??= $this->_resolve($selectedSaleIds, $store, $hasStock);
    }

    /**
     * Returns a store's enabled catalog pricing rules, built one at a time.
     *
     * Commerce builds every rule in a store together, so one row with a
     * malformed condition stops all of them loading. Here a rule that can't be
     * built is logged and left out, and the rest still work.
     *
     * @param int $storeId The store.
     * @param int[]|null $ids Only these rules, or null for all.
     * @return CatalogPricingRule[] The rules, keyed by ID.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public function getEnabledRules(int $storeId, ?array $ids = null): array
    {
        $query = (new Query())
            ->select([
                'apply',
                'applyAmount',
                'applyPriceType',
                'customerCondition',
                'dateCreated',
                'dateFrom',
                'dateTo',
                'dateUpdated',
                'description',
                'enabled',
                'id',
                'isPromotionalPrice',
                'metadata',
                'name',
                'productCondition',
                'purchasableCondition',
                'storeId',
                'variantCondition',
            ])
            ->from(CommerceTable::CATALOG_PRICING_RULES)
            ->where(['storeId' => $storeId, 'enabled' => true])
            ->orderBy(['id' => SORT_ASC]);

        if ($ids !== null) {
            $query->andWhere(['id' => $ids]);
        }

        $rules = [];

        foreach ($query->all() as $row) {
            foreach (['customerCondition', 'productCondition', 'purchasableCondition', 'variantCondition'] as $column) {
                $row[$column] ??= '';
            }

            try {
                $rule = Craft::createObject(CatalogPricingRule::class, ['config' => ['attributes' => $row]]);
                // Conditions are parsed lazily; reading them here keeps a broken one inside this try.
                $rule->getCustomerCondition();
                $rule->getProductCondition();
                $rule->getVariantCondition();
                $rule->getPurchasableCondition();
            } catch (Throwable $e) {
                Craft::warning("Skipped catalog pricing rule {$row['id']}, which couldn't be loaded: {$e->getMessage()}", 'pricing-rules-relationship');

                continue;
            }

            $rules[(int)$rule->id] = $rule;
        }

        return $rules;
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Works out the product IDs for a set of rules in one store.
     *
     * @param int[] $selectedSaleIds The rule IDs.
     * @param Store $store The store.
     * @param bool $hasStock Whether to restrict results to variants in stock.
     * @return int[] The product IDs.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _resolve(array $selectedSaleIds, Store $store, bool $hasStock): array
    {
        try {
            $selected = $this->getEnabledRules((int)$store->id, $selectedSaleIds);
        } catch (Throwable $e) {
            Craft::warning("Failed to load catalog pricing rules: {$e->getMessage()}", 'pricing-rules-relationship');

            return [];
        }

        $this->_expireCachesAtNextChange($selected);

        $now = time();
        $rules = array_filter(
            $selected,
            fn(CatalogPricingRule $rule): bool => ($rule->dateFrom === null || $rule->dateFrom->getTimestamp() <= $now)
                && ($rule->dateTo === null || $rule->dateTo->getTimestamp() >= $now)
                && $this->_currentUserMatchesRule($rule),
        );

        if ($rules === []) {
            return [];
        }

        $variantIds = [];

        foreach ($rules as $rule) {
            try {
                $ruleVariantIds = $rule->getPurchasableIds();
            } catch (Throwable $e) {
                // A rule that can't be resolved (say, a malformed condition) is
                // left out rather than risking a fatal on the front end.
                Craft::warning(
                    "Failed to resolve products for catalog pricing rule {$rule->id}: {$e->getMessage()}",
                    'pricing-rules-relationship',
                );

                continue;
            }

            // Null means the rule has no product, variant or purchasable
            // conditions, so it covers the whole catalogue.
            if ($ruleVariantIds === null) {
                $variantIds = null;
                break;
            }

            array_push($variantIds, ...$ruleVariantIds);
        }

        if ($variantIds === []) {
            return [];
        }

        $query = Variant::find()
            ->siteId($store->getSites()->map(fn(Site $site): int => (int)$site->id)->all())
            ->unique()
            ->status(null)
            ->productStatus('live');

        if ($variantIds !== null) {
            $query->id(array_values(array_unique($variantIds)));
        }

        if ($hasStock) {
            $query->hasStock();
        }

        try {
            $productIds = $query
                ->select(['commerce_variants.primaryOwnerId'])
                ->distinct()
                // MySQL won't combine DISTINCT with the default element ordering.
                ->orderBy([])
                ->column();
        } catch (Throwable $e) {
            Craft::warning("Failed to look up products for catalog pricing rules: {$e->getMessage()}", 'pricing-rules-relationship');

            return [];
        }

        return array_values(array_unique(array_map(static fn(mixed $id): int => (int)$id, array_filter($productIds))));
    }

    /**
     * Tells Craft's template and static caches to expire when the next of these
     * rules starts or ends, so a cached offers page doesn't outlive the offer.
     *
     * @param CatalogPricingRule[] $rules The enabled rules that were asked about.
     * @return void
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _expireCachesAtNextChange(array $rules): void
    {
        $now = new DateTime();
        $next = null;

        foreach ($rules as $rule) {
            foreach ([$rule->dateFrom, $rule->dateTo] as $date) {
                if ($date instanceof DateTime && $date > $now && ($next === null || $date < $next)) {
                    $next = $date;
                }
            }
        }

        if ($next !== null) {
            Craft::$app->getElements()->setCacheExpiryDate($next);
        }
    }

    /**
     * Returns whether the current user satisfies a rule's customer condition.
     *
     * A rule with no customer condition applies to everyone. A rule scoped to
     * customers only applies when the current user matches it; an anonymous
     * visitor never matches. If the condition can't be evaluated the user counts
     * as not eligible, so bad data never shows a rule's products to the wrong
     * people.
     *
     * @param CatalogPricingRule $rule The rule to test.
     * @return bool True when the rule applies to the current user.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
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
        } catch (Throwable $e) {
            Craft::warning(
                "Failed to evaluate catalog pricing rule customer condition: {$e->getMessage()}",
                'pricing-rules-relationship',
            );

            return false;
        }
    }
}

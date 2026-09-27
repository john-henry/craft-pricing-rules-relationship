<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\gql\resolvers;

use craft\commerce\gql\resolvers\elements\Product as ProductResolver;
use GraphQL\Type\Definition\ResolveInfo;
use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use yii\base\InvalidConfigException;

/**
 * Resolves the products a set of catalog pricing rules covers.
 *
 * The matching is the same as `craft.pricingRulesRelationship.getProductIds()`:
 * expired, disabled and not-yet-started rules count for nothing, and a rule
 * limited to a customer group only matches for customers in it. The matching
 * IDs are then handed to Commerce's own product resolver, so the schema's
 * product type permissions and every usual product argument still apply.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.1.0
 */
class PricingRuleProducts
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * Resolves the matching products.
     *
     * @param mixed $source The parent value, unused at the root.
     * @param array $arguments The query arguments.
     * @param mixed $context The GraphQL context.
     * @param ResolveInfo $resolveInfo The resolve info.
     * @return mixed The matching products.
     * @throws InvalidConfigException If the matching service can't be resolved.
     *
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        $arguments = self::_withMatchingIds($arguments);

        if ($arguments === null) {
            return [];
        }

        return ProductResolver::resolve($source, $arguments, $context, $resolveInfo);
    }

    /**
     * Resolves how many products match.
     *
     * @param mixed $source The parent value, unused at the root.
     * @param array $arguments The query arguments.
     * @param array|null $context The GraphQL context.
     * @param ResolveInfo $resolveInfo The resolve info.
     * @return mixed The number of matching products.
     * @throws InvalidConfigException If the matching service can't be resolved.
     *
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public static function resolveCount(mixed $source, array $arguments, ?array $context, ResolveInfo $resolveInfo): mixed
    {
        $arguments = self::_withMatchingIds($arguments);

        if ($arguments === null) {
            return 0;
        }

        return ProductResolver::resolveCount($source, $arguments, $context, $resolveInfo);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Swaps the rule arguments for the IDs of the products they cover.
     *
     * An `id` argument the query already had narrows the match rather than
     * replacing it, so a front end can't widen the result past the rules.
     *
     * @param array $arguments The query arguments.
     * @return array|null The product arguments, or null when nothing matches.
     * @throws InvalidConfigException If the matching service can't be resolved.
     *
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private static function _withMatchingIds(array $arguments): ?array
    {
        $ids = PricingRulesRelationship::getInstance()
            ->getPricingRulesRelationshipService()
            ->getMatchingProductsIds((array)($arguments['ruleIds'] ?? []), (bool)($arguments['hasStock'] ?? false));

        unset($arguments['ruleIds'], $arguments['hasStock']);

        if (isset($arguments['id'])) {
            $wanted = array_map(static fn(mixed $id): int => (int)$id, (array)$arguments['id']);
            $ids = array_values(array_intersect($ids, $wanted));
        }

        if ($ids === []) {
            return null;
        }

        $arguments['id'] = $ids;

        return $arguments;
    }
}

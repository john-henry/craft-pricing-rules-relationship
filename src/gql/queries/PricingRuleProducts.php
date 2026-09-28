<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\gql\queries;

use craft\commerce\gql\arguments\elements\Product as ProductArguments;
use craft\commerce\gql\interfaces\elements\Product as ProductInterface;
use craft\commerce\helpers\Gql as CommerceGqlHelper;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use johnhenry\pricingrulesrelationship\gql\resolvers\PricingRuleProducts as PricingRuleProductsResolver;

/**
 * GraphQL queries for the products a set of catalog pricing rules covers.
 *
 * The field itself returns rule IDs; these queries turn them into products, so
 * a headless front end can build an offer page the way a Twig template does
 * with `craft.pricingRulesRelationship.getProductIds()`.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.1.0
 */
class PricingRuleProducts extends Query
{
    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @param bool $checkToken Whether to only return the queries the current token's schema allows.
     * @return array The queries, keyed by name.
     *
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !CommerceGqlHelper::canQueryProducts()) {
            return [];
        }

        $arguments = array_merge(ProductArguments::getArguments(), [
            'ruleIds' => [
                'name' => 'ruleIds',
                'type' => Type::nonNull(Type::listOf(Type::int())),
                'description' => 'The catalog pricing rule IDs, usually the Pricing Rules Relationship field value.',
            ],
            'hasStock' => [
                'name' => 'hasStock',
                'type' => Type::boolean(),
                'description' => 'Only products with a variant in stock.',
            ],
        ]);

        return [
            'pricingRuleProducts' => [
                'type' => Type::listOf(ProductInterface::getType()),
                'args' => $arguments,
                'resolve' => PricingRuleProductsResolver::class . '::resolve',
                'description' => 'The products the given catalog pricing rules cover, for the current store and customer.',
            ],
            'pricingRuleProductCount' => [
                'type' => Type::nonNull(Type::int()),
                'args' => $arguments,
                'resolve' => PricingRuleProductsResolver::class . '::resolveCount',
                'description' => 'The number of products the given catalog pricing rules cover.',
            ],
        ];
    }
}

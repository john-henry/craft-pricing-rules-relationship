<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\base;

use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterGqlQueriesEvent;
use craft\services\Fields;
use craft\services\Gql;
use craft\web\twig\variables\CraftVariable;
use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;
use johnhenry\pricingrulesrelationship\gql\queries\PricingRuleProducts;
use johnhenry\pricingrulesrelationship\variables\PricingRulesRelationshipVariable;
use yii\base\Event;

/**
 * PluginTrait
 *
 * Wires the plugin's field type, Twig variable and GraphQL queries. Keeps the main plugin class
 * a thin shell: the listeners here only register components and never carry
 * domain logic themselves.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.4
 */
trait PluginTrait
{
    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Registers the plugin's field type.
     *
     * @return void
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    private function _registerField(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = PricingRulesRelationshipField::class;
            }
        );
    }

    /**
     * Registers the GraphQL queries for the products a set of rules covers.
     *
     * @return void
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _registerGqlQueries(): void
    {
        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            static function(RegisterGqlQueriesEvent $event) {
                $event->queries = array_merge($event->queries, PricingRuleProducts::getQueries());
            }
        );
    }

    /**
     * Registers the Twig variable for use in templates.
     *
     * @return void
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('pricingRulesRelationship', PricingRulesRelationshipVariable::class);
            }
        );
    }
}

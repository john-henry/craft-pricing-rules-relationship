<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship;

use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;
use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;
use johnhenry\pricingrulesrelationship\services\PricingRulesRelationshipService;
use johnhenry\pricingrulesrelationship\variables\PricingRulesRelationshipVariable;
use yii\base\Event;

/**
 * Pricing Rules Relationship plugin: relates Commerce catalog pricing rules to elements.
 *
 * @method static PricingRulesRelationship getInstance()
 * @property PricingRulesRelationshipService $pricingRulesRelationshipService
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationship extends BasePlugin
{
    // =========================================================================
    // Properties
    // =========================================================================

    /** @var PricingRulesRelationship */
    public static PricingRulesRelationship $plugin;

    // =========================================================================
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function config(): array
    {
        return [
            'components' => [
                'pricingRulesRelationshipService' => PricingRulesRelationshipService::class,
            ],
        ];
    }

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerField();
        $this->_registerVariable();
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Registers the field type.
     */
    private function _registerField(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = PricingRulesRelationshipField::class;
            }
        );
    }

    /**
     * Registers the Twig variable.
     */
    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                $event->sender->set('pricingRulesRelationship', PricingRulesRelationshipVariable::class);
            }
        );
    }
}

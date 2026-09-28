<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship;

use craft\base\Plugin as BasePlugin;
use johnhenry\pricingrulesrelationship\base\PluginTrait;
use johnhenry\pricingrulesrelationship\services\PricingRulesRelationshipService;
use yii\base\InvalidConfigException;

/**
 * Pricing Rules Relationship plugin: relates Commerce catalog pricing rules to elements.
 *
 * @method static PricingRulesRelationship getInstance()
 * @property-read PricingRulesRelationshipService $pricingRulesRelationshipService
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationship extends BasePlugin
{
    // =========================================================================
    // Traits
    // =========================================================================

    use PluginTrait;

    // =========================================================================
    // Static Properties
    // =========================================================================

    /**
     * @var PricingRulesRelationship The plugin instance.
     */
    public static PricingRulesRelationship $plugin;

    /**
     * @inheritdoc
     *
     * @return array The plugin's component configuration.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
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
     *
     * @return void
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerField();
        $this->_registerVariable();
        $this->_registerGqlQueries();
    }

    /**
     * Returns the pricing rules relationship service.
     *
     * @return PricingRulesRelationshipService The service.
     * @throws InvalidConfigException If the component cannot be resolved.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.4
     */
    public function getPricingRulesRelationshipService(): PricingRulesRelationshipService
    {
        $component = $this->get('pricingRulesRelationshipService');
        assert($component instanceof PricingRulesRelationshipService);

        return $component;
    }
}

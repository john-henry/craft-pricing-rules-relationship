<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship;

use Craft;
use craft\base\Plugin as BasePlugin;

use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;

use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;
use johnhenry\pricingrulesrelationship\services\PricingRulesRelationshipService;
use johnhenry\pricingrulesrelationship\variables\PricingRulesRelationshipVariable;

use yii\base\Event;

/**
 * Pricing Rules Relationship plugin
 *
 * @method static PricingRulesRelationship getInstance()
 * @property PricingRulesRelationshipService $pricingRulesRelationshipService
 * @author John Henry Donovan <info@johnhenry.ie>
 * @copyright John Henry Donovan
 * @license https://craftcms.github.io/license/ Craft License
 */
class PricingRulesRelationship extends BasePlugin
{
    /**
     * @var PricingRulesRelationship
     */
    public static PricingRulesRelationship $plugin;


    public static function config(): array
    {
        return [
            'components' => [
                'pricingRulesRelationshipService' => PricingRulesRelationshipService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->_registerVariable();

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerField();
        }
    }

    private function _registerField(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = PricingRulesRelationshipField::class;
            });
    }

    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                $event->sender->set('pricingRulesRelationship', PricingRulesRelationshipVariable::class);
            });
    }
}

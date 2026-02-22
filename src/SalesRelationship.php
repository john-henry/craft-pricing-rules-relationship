<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\salesrelationship;

use Craft;
use craft\base\Plugin as BasePlugin;

use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;

use johnhenry\salesrelationship\fields\SalesRelationshipField;
use johnhenry\salesrelationship\services\SalesRelationshipService;
use johnhenry\salesrelationship\variables\SalesRelationshipVariable;

use yii\base\Event;

/**
 * Sales Relationship plugin
 *
 * @method static SalesRelationship getInstance()
 * @property SalesRelationshipService $salesRelationshipService
 * @author John Henry Donovan <info@johnhenry.ie>
 * @copyright John Henry Donovan
 * @license https://craftcms.github.io/license/ Craft License
 */
class SalesRelationship extends BasePlugin
{
    /**
     * @var SalesRelationship
     */
    public static SalesRelationship $plugin;


    public static function config(): array
    {
        return [
            'components' => [
                'salesRelationshipService' => SalesRelationshipService::class,
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
                $event->types[] = SalesRelationshipField::class;
            });
    }

    private function _registerVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event) {
                $event->sender->set('salesRelationship', SalesRelationshipVariable::class);
            });
    }
}

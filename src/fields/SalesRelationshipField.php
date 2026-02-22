<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\salesrelationship\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\Plugin as Commerce;
use craft\errors\SiteNotFoundException;
use DateTime;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;

class SalesRelationshipField extends Field
{
    public static function displayName(): string
    {
        return 'Sales Relationship';
    }


    public string $defaultText = '';


    /**
     * @throws SiteNotFoundException
     * @throws SyntaxError
     * @throws InvalidConfigException
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getInputHtml(mixed $value, ?ElementInterface $element): string
    {
        return Craft::$app->getView()->renderTemplate('sales-relationship/_input', [
            'name' => $this->handle,
            'field' => $this,
            'value' => $value,
            'options' => $this->getSales($element),
        ]);
    }


    /**
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    private function getSales(ElementInterface $element = null): array
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();

        $siteId = $element?->siteId ?? $currentSite->id;
        $store = Commerce::getInstance()->getStores()->getStoreBySiteId($siteId);

        if ($store === null) {
            return [
                'error' => Craft::t('sales-relationship', 'No store available for this site'),
                'sales' => [],
            ];
        }

        $sales = Commerce::getInstance()->getCatalogPricingRules()->getAllCatalogPricingRules($store->id);

        $activeSales = [];
        $now = new DateTime();

        foreach ($sales as $sale) {
            if ($sale->dateTo !== null && $sale->dateTo <= $now) {
                continue;
            }

            $activeSales[] = [
                'id' => $sale->id,
                'name' => $sale->name,
                'dateTo' => $sale->dateTo,
            ];
        }

        return [
            'error' => null,
            'sales' => $activeSales,
        ];
    }
}

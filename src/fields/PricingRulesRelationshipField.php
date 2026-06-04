<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\fields;

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

/**
 * Relates Commerce catalog pricing rules to any element via a checkbox list.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationshipField extends Field
{
    // =========================================================================
    // Properties
    // =========================================================================

    /** @var string Placeholder text shown when no pricing rules are available. */
    public string $defaultText = '';

    /** @var bool Whether to show the "New Catalog Pricing Rule" button in the field UI. */
    public bool $showNewRuleButton = true;

    /** @var bool Whether to show pricing rule expiry dates beside each option. */
    public bool $showRuleExpiryDates = true;

    // =========================================================================
    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Pricing Rules Relationship';
    }

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('pricing-rules-relationship/_settings', [
            'field' => $this,
        ]);
    }

    /**
     * @inheritdoc
     * @throws SiteNotFoundException
     * @throws SyntaxError
     * @throws InvalidConfigException
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getInputHtml(mixed $value, ?ElementInterface $element): string
    {
        $options = $this->_getSales($element);

        return Craft::$app->getView()->renderTemplate('pricing-rules-relationship/_input', [
            'name' => $this->handle,
            'field' => $this,
            'value' => $value,
            'options' => $options,
            'storeHandle' => $options['storeHandle'],
        ]);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Returns active pricing rules for the element's store, plus the store handle.
     *
     * @return array{error: string|null, sales: array, storeHandle: string}
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    private function _getSales(?ElementInterface $element = null): array
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteId = $element?->siteId ?? $currentSite->id;
        $store = Commerce::getInstance()->getStores()->getStoreBySiteId($siteId);

        if ($store === null) {
            return [
                'error' => Craft::t('pricing-rules-relationship', 'No store available for this site'),
                'sales' => [],
                'storeHandle' => 'primary',
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
            'storeHandle' => $store->handle,
        ];
    }
}

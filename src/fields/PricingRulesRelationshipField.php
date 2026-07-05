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
use craft\helpers\Json;
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
     *
     * The value is stored as a JSON array of ID strings, so we decode it back
     * into a clean list of unique integer IDs. That way templates always get an
     * int[] to work with rather than the raw JSON string.
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $id) {
            if (is_int($id) || (is_string($id) && $id !== '' && ctype_digit($id))) {
                $ids[] = (int)$id;
            }
        }

        return array_values(array_unique($ids));
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

        // Work out how many saved rules are no longer in the list, so we can
        // warn the editor they'll drop off on the next save.
        $selectedIds = is_array($value) ? array_map('intval', $value) : [];
        $availableIds = array_map(static fn(array $sale): int => (int)$sale['id'], $options['sales']);
        $missingCount = count(array_diff($selectedIds, $availableIds));

        return Craft::$app->getView()->renderTemplate('pricing-rules-relationship/_input', [
            'name' => $this->handle,
            'field' => $this,
            'value' => $value,
            'options' => $options,
            'storeHandle' => $options['storeHandle'],
            'missingCount' => $missingCount,
        ]);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Returns active pricing rules for the element's store, plus the store handle.
     *
     * If there's no store for the site, the handle comes back as null so the
     * template can hide the "New Catalog Pricing Rule" button rather than point
     * it at a guessed handle.
     *
     * @return array{error: string|null, sales: array, storeHandle: string|null}
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
                'storeHandle' => null,
            ];
        }

        // Only offer rules that are currently active: enabled and inside their
        // date window. This is Commerce's own definition of an active rule, and
        // it's the same set the service matches against, so a rule you can tick
        // here is a rule that can actually return products.
        $sales = Commerce::getInstance()->getCatalogPricingRules()->getAllActiveCatalogPricingRules($store->id);

        $activeSales = [];

        foreach ($sales as $sale) {
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

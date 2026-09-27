<?php

/**
 * @copyright Copyright (c) John Henry Donovan
 */

namespace johnhenry\pricingrulesrelationship\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\commerce\db\Table as CommerceTable;
use craft\commerce\models\CatalogPricingRule;
use craft\commerce\models\Store;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\db\QueryParam;
use craft\errors\SiteNotFoundException;
use craft\helpers\Db;
use craft\helpers\Json;
use DateTime;
use GraphQL\Type\Definition\Type;
use johnhenry\pricingrulesrelationship\PricingRulesRelationship;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\ExpressionInterface;
use yii\db\Schema;

/**
 * Relates Commerce catalog pricing rules to any element via a checkbox list.
 *
 * Rules that are running or scheduled to start are offered, so expired and
 * switched-off rules stay out of the editor's way. They aren't removed from the value, though: saving keeps
 * any stored rule the editor wasn't shown, so a rule that's switched back on or
 * extended comes back by itself. Only rules deleted from Commerce are dropped.
 *
 * @author John Henry Donovan <info@johnhenry.ie>
 * @since 1.0.0
 */
class PricingRulesRelationshipField extends Field
{
    // =========================================================================
    // Const Properties
    // =========================================================================

    /**
     * @var int Most rules one field can hold, so a crafted request can't store a
     * list large enough to slow every page that reads it.
     */
    public const MAX_RULES = 100;

    /**
     * @var string Commerce permission for managing promotions. Editors without it
     * are only offered rules open to every customer.
     */
    public const PERMISSION_MANAGE_PROMOTIONS = 'commerce-managePromotions';

    // =========================================================================
    // Public Properties
    // =========================================================================

    /**
     * @var string Placeholder text shown when no pricing rules are available.
     */
    public string $defaultText = '';

    /**
     * @var bool Whether to show the "New Catalog Pricing Rule" button in the field UI.
     */
    public bool $showNewRuleButton = true;

    /**
     * @var bool Whether to show pricing rule expiry dates beside each option.
     */
    public bool $showRuleExpiryDates = true;

    // =========================================================================
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @return string The field type's name.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    public static function displayName(): string
    {
        return Craft::t('pricing-rules-relationship', 'Pricing Rules Relationship');
    }

    /**
     * @inheritdoc
     *
     * A group of checkboxes, so the label belongs on a fieldset legend rather
     * than pointing at a single input.
     *
     * @return bool Always true.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public function useFieldset(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * Rule IDs mean nothing to a search, so nothing is indexed.
     *
     * @param mixed $value The field value.
     * @param ElementInterface $element The element the field belongs to.
     * @return string An empty string.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return '';
    }

    /**
     * @inheritdoc
     *
     * @return string The column type: the value is a JSON list of rule IDs.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public static function dbType(): string
    {
        return Schema::TYPE_JSON;
    }

    /**
     * Lets an element query find elements by the rules they hold, as in
     * `entries.salesRelationship(12)`, `[12, 14]` for either, `['and', 12, 14]`
     * for both, `'not 12'`, or `':notempty:'`.
     *
     * @param static[] $instances The field instances being queried.
     * @param mixed $value The query param.
     * @param array $params Parameters to bind.
     * @return array|string|ExpressionInterface|false|null The condition.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public static function queryCondition(array $instances, mixed $value, array &$params): array|string|ExpressionInterface|false|null
    {
        $param = QueryParam::parse($value);

        if ($param->values === []) {
            return null;
        }

        $valueSql = static::valueSql($instances);

        if ($valueSql === null) {
            return false;
        }

        $negate = $param->operator === QueryParam::NOT;
        $condition = [$negate ? QueryParam::OR : $param->operator];
        $qb = Craft::$app->getDb()->getQueryBuilder();

        foreach ($param->values as $ruleId) {
            if (is_string($ruleId) && in_array(strtolower($ruleId), [':empty:', ':notempty:', 'not :empty:'], true)) {
                $condition[] = Db::parseParam($valueSql, $ruleId, columnType: Schema::TYPE_JSON);

                continue;
            }

            // Values saved before 1.0.3 may hold the ID as a string.
            $condition[] = [
                'or',
                $qb->jsonContains($valueSql, (int)$ruleId),
                $qb->jsonContains($valueSql, (string)(int)$ruleId),
            ];
        }

        // An element with nothing stored doesn't hold the rule either.
        return $negate ? ['or', [$valueSql => null], ['not', $condition]] : $condition;
    }

    /**
     * @inheritdoc
     *
     * @return string|null The settings HTML.
     * @throws LoaderError|RuntimeError|SyntaxError|Exception If the template can't be rendered.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
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
     * The value is stored as a JSON array of IDs, so it's decoded back into a
     * clean list of unique integer IDs. Templates always get an int[] rather than
     * the raw JSON string.
     *
     * @param mixed $value The raw value.
     * @param ElementInterface|null $element The element the field belongs to.
     * @return int[] The rule IDs.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): array
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

        return array_slice(array_values(array_unique($ids)), 0, self::MAX_RULES);
    }

    /**
     * Keeps only the rules this editor was offered from what they posted, and
     * carries over every stored rule they weren't shown, so saving never drops
     * an expired, switched-off or hidden rule. Rules deleted from Commerce are
     * dropped.
     *
     * @param mixed $value The posted value.
     * @param ElementInterface|null $element The element the field belongs to.
     * @return int[] The rule IDs to store.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public function normalizeValueFromRequest(mixed $value, ?ElementInterface $element): array
    {
        $offered = array_flip(array_map(
            static fn(CatalogPricingRule $rule): int => (int)$rule->id,
            $this->_offeredRules($this->_storeFor($element)),
        ));

        $posted = array_values(array_filter(
            $this->normalizeValue($value, $element),
            static fn(int $id): bool => isset($offered[$id]),
        ));

        $kept = array_values(array_filter(
            $this->_storedValue($element),
            static fn(int $id): bool => !isset($offered[$id]),
        ));

        $existing = array_flip($this->_existingRuleIds(array_merge($posted, $kept)));

        $ids = array_values(array_filter(
            array_values(array_unique(array_merge($kept, $posted))),
            static fn(int $id): bool => isset($existing[$id]),
        ));

        return array_slice($ids, 0, self::MAX_RULES);
    }

    /**
     * @inheritdoc
     *
     * @return Type The GraphQL type: a list of rule IDs.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public function getContentGqlType(): Type
    {
        return Type::listOf(Type::int());
    }

    /**
     * @inheritdoc
     *
     * @return Type The GraphQL mutation argument type: a list of rule IDs.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    public function getContentGqlMutationArgumentType(): Type
    {
        return Type::listOf(Type::int());
    }

    // =========================================================================
    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @param mixed $value The field value.
     * @param ElementInterface|null $element The element the field belongs to.
     * @param bool $inline Whether this is for an inline edit form.
     * @return string The input HTML.
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @throws LoaderError|RuntimeError|SyntaxError|Exception If the template can't be rendered.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.0.0
     */
    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $store = $this->_storeFor($element);
        $selectedIds = $this->normalizeValue($value, $element);
        $userService = Craft::$app->getUser();

        $now = new DateTime();
        $rules = array_map(static fn(CatalogPricingRule $rule): array => [
            'id' => (int)$rule->id,
            'name' => $rule->name,
            'dateTo' => $rule->dateTo,
            'startsLater' => $rule->dateFrom !== null && $rule->dateFrom > $now ? $rule->dateFrom : null,
        ], $this->_offeredRules($store));

        // Only rules deleted from Commerce are worth a warning: they're dropped on
        // save. Expired, switched-off and hidden rules are kept quietly.
        $deletedCount = count(array_diff($selectedIds, $this->_existingRuleIds($selectedIds)));

        return Craft::$app->getView()->renderTemplate('pricing-rules-relationship/_input', [
            'name' => $this->handle,
            'field' => $this,
            'value' => $selectedIds,
            'rules' => $rules,
            'storeError' => $store === null
                ? Craft::t('pricing-rules-relationship', 'No store available for this site')
                : null,
            'storeHandle' => $store?->handle,
            'deletedCount' => $deletedCount,
            // Commerce's new-rule screen needs both permissions.
            'canCreateRules' => $userService->checkPermission(self::PERMISSION_MANAGE_PROMOTIONS)
                && $userService->checkPermission('commerce-createCatalogPricingRules'),
            // With legacy Sales in place, Commerce ignores catalog pricing rules.
            'legacySales' => $store !== null && !$this->_catalogPricingRulesInUse(),
        ]);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Returns the store for the element's site, or the current site's.
     *
     * @param ElementInterface|null $element The element the field belongs to.
     * @return Store|null The store, or null if the site has none.
     * @throws SiteNotFoundException If there is no current site.
     * @throws InvalidConfigException If Commerce can't resolve its stores.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _storeFor(?ElementInterface $element): ?Store
    {
        $siteId = $element->siteId ?? Craft::$app->getSites()->getCurrentSite()->id;

        return Commerce::getInstance()->getStores()->getStoreBySiteId($siteId);
    }

    /**
     * Returns whether Commerce is pricing with catalog pricing rules rather than
     * legacy Sales.
     *
     * @return bool True if catalog pricing rules are in use.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _catalogPricingRulesInUse(): bool
    {
        try {
            return Commerce::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules();
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * Returns the rules the current user is offered as checkboxes.
     *
     * Enabled rules that haven't ended are offered, including ones scheduled to
     * start later, so a campaign can be set up ahead of its sale. A user
     * without Commerce's promotions permission is only offered rules open to
     * every customer, since a rule's name can give away a deal made with one.
     *
     * @param Store|null $store The store, or null.
     * @return CatalogPricingRule[] The offered rules.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _offeredRules(?Store $store): array
    {
        if ($store === null) {
            return [];
        }

        try {
            $now = time();
            $rules = array_filter(
                PricingRulesRelationship::getInstance()->getPricingRulesRelationshipService()->getEnabledRules((int)$store->id),
                static fn(CatalogPricingRule $rule): bool => $rule->dateTo === null || $rule->dateTo->getTimestamp() >= $now,
            );
        } catch (Throwable $e) {
            Craft::warning("Failed to load catalog pricing rules: {$e->getMessage()}", 'pricing-rules-relationship');

            return [];
        }

        if (Craft::$app->getUser()->checkPermission(self::PERMISSION_MANAGE_PROMOTIONS)) {
            return array_values($rules);
        }

        return array_values(array_filter($rules, static function(CatalogPricingRule $rule): bool {
            try {
                return $rule->getCustomerCondition()->getConditionRules() === [];
            } catch (Throwable) {
                return false;
            }
        }));
    }

    /**
     * Returns the rule IDs stored on the element before this request.
     *
     * @param ElementInterface|null $element The element the field belongs to.
     * @return int[] The stored IDs.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _storedValue(?ElementInterface $element): array
    {
        if ($element === null) {
            return [];
        }

        try {
            return $this->normalizeValue($element->getFieldValue($this->handle), $element);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Returns which of the given IDs still belong to a catalog pricing rule, in
     * any store and whatever its status.
     *
     * @param int[] $ids The IDs to check.
     * @return int[] The IDs that still exist.
     * @author John Henry Donovan <info@johnhenry.ie>
     * @since 1.1.0
     */
    private function _existingRuleIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_map(static fn(mixed $id): int => (int)$id, (new Query())
            ->select(['id'])
            ->from(CommerceTable::CATALOG_PRICING_RULES)
            ->where(['id' => $ids])
            ->column());
    }
}

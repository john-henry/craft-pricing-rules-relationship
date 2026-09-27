<?php

use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\helpers\StringHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use johnhenry\pricingrulesrelationship\fields\PricingRulesRelationshipField;
use markhuot\craftpest\factories\User as UserFactory;

// Field inputs are control panel templates.
beforeEach(function() {
    Craft::$app->getView()->setTemplateMode(craft\web\View::TEMPLATE_MODE_CP);
});

// ---------------------------------------------------------------------------
// Field save → reload round-trip
//
// Save an entry with a few rule IDs ticked, reload it fresh from the database,
// and check the value comes back as a proper int[] and that re-rendering the
// input template ticks the right boxes.
//
// The section, entry type and field layout are built straight through the core
// services rather than the craft-pest Section factory: that factory doesn't
// reliably persist a plain custom field into the layout, so the layout reloads
// empty and the entry rejects the handle.
// ---------------------------------------------------------------------------

/**
 * Build (but do not save) an entry on the given section's first entry type.
 */
function makeEntryOn(Section $section): Entry
{
    $entryType = $section->getEntryTypes()[0];

    $entry = new Entry();
    $entry->sectionId = $section->id;
    $entry->typeId = $entryType->id;
    $entry->title = 'Round-trip test entry';

    return $entry;
}


describe('PricingRulesRelationshipField save/reload round-trip', function() {
    beforeEach(function() {
        $handle = 'prrField' . StringHelper::randomString(6);

        // 1. Save the field.
        $field = new PricingRulesRelationshipField();
        $field->name = 'PRR Round Trip';
        $field->handle = $handle;
        expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
        $this->field = Craft::$app->getFields()->getFieldByHandle($handle);

        // 2. Save an entry type whose field layout contains the field.
        $layout = FieldLayout::createFromConfig([
            'tabs' => [
                [
                    'name' => 'Content',
                    'elements' => [
                        [
                            'type' => craft\fieldlayoutelements\CustomField::class,
                            'fieldUid' => $this->field->uid,
                        ],
                    ],
                ],
            ],
        ]);
        $layout->type = Entry::class;

        $entryType = new EntryType([
            'name' => 'PRR Round Trip',
            'handle' => 'prrRoundTrip' . StringHelper::randomString(6),
        ]);
        $entryType->setFieldLayout($layout);
        expect(Craft::$app->getEntries()->saveEntryType($entryType))->toBeTrue();

        // 3. Save a section using that entry type.
        $sectionHandle = 'prrSection' . StringHelper::randomString(6);
        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new Section_SiteSettings([
                'siteId' => $site->id,
                'hasUrls' => false,
            ]);
        }

        $section = new Section([
            'name' => 'PRR Section',
            'handle' => $sectionHandle,
            'type' => Section::TYPE_CHANNEL,
            'propagationMethod' => PropagationMethod::All,
            'siteSettings' => $siteSettings,
        ]);
        $section->setEntryTypes([$entryType]);
        expect(Craft::$app->getEntries()->saveSection($section))->toBeTrue();

        $this->section = $section;
    });

    it('reads the stored value back as a deduplicated int array', function() {
        $entry = makeEntryOn($this->section);
        $entry->setFieldValue($this->field->handle, ['3', '27', '3']);

        expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();

        // Reload from the database to exercise the read/normalize path.
        $reloaded = Entry::find()->id($entry->id)->status(null)->one();
        $value = $reloaded->getFieldValue($this->field->handle);

        expect($value)->toBe([3, 27]);
    });

    it('lets an element query find entries by the rules they hold', function() {
        $save = function(array $ids): int {
            $entry = makeEntryOn($this->section);
            $entry->setFieldValue($this->field->handle, $ids);
            Craft::$app->getElements()->saveElement($entry);

            return $entry->id;
        };

        $a = $save([3, 27]);
        $b = $save([27]);
        $c = $save([]);
        $handle = $this->field->handle;
        $find = fn(mixed $param): array => sortedIds(Entry::find()->sectionId($this->section->id)->status(null)->$handle($param)->ids());

        expect($find(3))->toBe([$a])
            ->and($find(27))->toBe(sortedIds([$a, $b]))
            ->and($find([3, 99]))->toBe([$a])
            ->and($find(['and', 3, 27]))->toBe([$a])
            ->and($find('not 3'))->toBe(sortedIds([$b, $c]))
            ->and($find(':notempty:'))->toBe(sortedIds([$a, $b]));
    });

    it('reads an empty selection back as an empty array', function() {
        $entry = makeEntryOn($this->section);
        $entry->setFieldValue($this->field->handle, []);

        expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();

        $reloaded = Entry::find()->id($entry->id)->status(null)->one();

        expect($reloaded->getFieldValue($this->field->handle))->toBe([]);
    });

    it('ticks the saved rules when the input is rendered', function() {
        $this->actingAs(UserFactory::factory()->admin(true)->create());
        $a = insertPricingRule(['name' => 'Rule A']);
        $b = insertPricingRule(['name' => 'Rule B']);

        $entry = makeEntryOn($this->section);
        $entry->setFieldValue($this->field->handle, [$a]);
        Craft::$app->getElements()->saveElement($entry);

        $reloaded = Entry::find()->id($entry->id)->status(null)->one();
        $html = $this->field->getInputHtml($reloaded->getFieldValue($this->field->handle), $reloaded);

        expect($html)
            ->toMatch('/value="' . $a . '"[^>]*checked/')
            ->not->toMatch('/value="' . $b . '"[^>]*checked/');
    });

});

// ---------------------------------------------------------------------------
// Saving from the edit form
//
// These go through setFieldValueFromRequest(), the path the control panel
// uses, so the hidden empty input, the kept hidden rules and the permission
// filtering are all exercised as they are in a real save.
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField saving from the edit form', function() {
    beforeEach(function() {
        $this->actingAs(UserFactory::factory()->admin(true)->create());
        $this->field = new PricingRulesRelationshipField(['handle' => 'prrForm']);
    });

    it('clears the field when every box is unticked', function() {
        $rule = insertPricingRule();

        expect($this->field->normalizeValueFromRequest('', null))->toBe([])
            ->and($this->field->getInputHtml([$rule], null))->toContain('type="hidden" name="prrForm" value=""');
    });

    it('keeps an expired or switched-off rule the editor was not shown', function() {
        $live = insertPricingRule(['name' => 'Live']);
        $expired = insertPricingRule(['name' => 'Expired', 'dateTo' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))]);
        $off = insertPricingRule(['name' => 'Off', 'enabled' => 0]);

        $html = $this->field->getInputHtml([$live, $expired, $off], null);

        expect($html)->not->toContain('Expired')
            ->not->toContain('>Off<')
            ->not->toContain('warning has-icon');

        $saved = PrrFieldProbe::fromRequest($this->field, [], [$live, $expired, $off]);

        expect(sortedIds($saved))->toEqual(sortedIds([$expired, $off]));
    });

    it('drops a rule deleted from Commerce, and says so first', function() {
        $kept = insertPricingRule();
        $deleted = insertPricingRule();
        Craft::$app->getDb()->createCommand()->delete('{{%commerce_catalogpricingrules}}', ['id' => $deleted])->execute();
        clearPricingRuleMemo();

        expect($this->field->getInputHtml([$kept, $deleted], null))->toContain('deleted from Commerce');

        $saved = PrrFieldProbe::fromRequest($this->field, [(string)$kept], [$kept, $deleted]);

        expect($saved)->toBe([$kept]);
    });

    it('ignores a posted rule the editor was not offered', function() {
        $offered = insertPricingRule();
        $expired = insertPricingRule(['dateTo' => gmdate('Y-m-d H:i:s', strtotime('-1 day'))]);

        $saved = PrrFieldProbe::fromRequest($this->field, [(string)$offered, (string)$expired, '999999999'], []);

        expect($saved)->toBe([$offered]);
    });

    it('offers a rule that has not started yet, marked with its start date', function() {
        insertPricingRule(['name' => 'Next Month', 'dateFrom' => gmdate('Y-m-d H:i:s', strtotime('+30 days'))]);

        expect($this->field->getInputHtml([], null))->toContain('Next Month')->toContain('starts');
    });
});

describe('PricingRulesRelationshipField for an editor without promotions permission', function() {
    it('only offers rules open to every customer', function() {
        $this->actingAs(UserFactory::factory()->create());
        $field = new PricingRulesRelationshipField(['handle' => 'prrEditor', 'showNewRuleButton' => true]);

        $group = new craft\models\UserGroup(['name' => 'PRR Hidden ' . uniqid(), 'handle' => 'prrHidden' . str_replace('.', '', uniqid('', true))]);
        Craft::$app->getUserGroups()->saveGroup($group);
        $conditionRule = new craft\elements\conditions\users\GroupConditionRule();
        $conditionRule->setValues([$group->uid]);
        $condition = new craft\commerce\elements\conditions\customers\CatalogPricingRuleCustomerCondition();
        $condition->setConditionRules([$conditionRule]);

        insertPricingRule(['name' => 'Everyone Sale']);
        insertPricingRule(['name' => 'Acme Contract', 'customerCondition' => craft\helpers\Json::encode($condition->getConfig())]);

        $html = $field->getInputHtml([], null);

        expect($html)->toContain('Everyone Sale')
            ->not->toContain('Acme Contract')
            ->not->toContain('New Catalog Pricing Rule');
    });
});

describe('PricingRulesRelationshipField over GraphQL', function() {
    it('describes its value as a list of rule IDs', function() {
        $field = new PricingRulesRelationshipField();

        expect((string)$field->getContentGqlType())->toBe('[Int]')
            ->and((string)$field->getContentGqlMutationArgumentType())->toBe('[Int]');
    });
});

/**
 * Runs a posted value through the field the way the edit form does, on an
 * element that already holds `$stored`.
 */
class PrrFieldProbe
{
    /**
     * @param int[] $stored
     * @return int[]
     */
    public static function fromRequest(PricingRulesRelationshipField $field, mixed $posted, array $stored): array
    {
        $element = new class($field, $stored) extends Entry {
            public function __construct(private PricingRulesRelationshipField $probeField, private array $probeStored)
            {
                parent::__construct();
            }

            public function getFieldValue(string $fieldHandle): mixed
            {
                return $fieldHandle === $this->probeField->handle ? $this->probeStored : parent::getFieldValue($fieldHandle);
            }
        };

        return $field->normalizeValueFromRequest($posted, $element);
    }
}

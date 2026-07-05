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

/**
 * Render the field's input partial with a supplied option set and return the HTML.
 * storeHandle is left null so the "New Catalog Pricing Rule" button is skipped.
 */
function renderPrrInput(array $value, array $sales, int $missingCount): string
{
    $field = new PricingRulesRelationshipField();
    $field->handle = 'prrStale';

    $view = Craft::$app->getView();
    $originalMode = $view->getTemplateMode();
    $view->setTemplateMode(craft\web\View::TEMPLATE_MODE_CP);

    try {
        return $view->renderTemplate('pricing-rules-relationship/_input', [
            'field' => $field,
            'value' => $value,
            'options' => [
                'error' => null,
                'sales' => $sales,
                'storeHandle' => null,
            ],
            'storeHandle' => null,
            'missingCount' => $missingCount,
        ]);
    } finally {
        $view->setTemplateMode($originalMode);
    }
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

    it('reads an empty selection back as an empty array', function() {
        $entry = makeEntryOn($this->section);
        $entry->setFieldValue($this->field->handle, []);

        expect(Craft::$app->getElements()->saveElement($entry))->toBeTrue();

        $reloaded = Entry::find()->id($entry->id)->status(null)->one();

        expect($reloaded->getFieldValue($this->field->handle))->toBe([]);
    });

    it('renders the correct checked state per option after reload', function() {
        // A logged-in user is required for the template's currentUser.can() gate.
        $this->actingAs(UserFactory::factory()->admin(true)->create());

        $entry = makeEntryOn($this->section);
        $entry->setFieldValue($this->field->handle, ['3', '27']);

        Craft::$app->getElements()->saveElement($entry);

        $reloaded = Entry::find()->id($entry->id)->status(null)->one();
        $value = $reloaded->getFieldValue($this->field->handle);

        // Render the input partial directly with a known option set so the
        // assertion does not depend on which pricing rules exist in the store.
        // Plugin CP templates resolve under the CP template mode.
        $view = Craft::$app->getView();
        $originalMode = $view->getTemplateMode();
        $view->setTemplateMode(craft\web\View::TEMPLATE_MODE_CP);

        try {
            $html = $view->renderTemplate('pricing-rules-relationship/_input', [
                'field' => $this->field,
                'value' => $value,
                'options' => [
                    'error' => null,
                    'sales' => [
                        ['id' => 3, 'name' => 'Rule 3', 'dateTo' => null],
                        ['id' => 5, 'name' => 'Rule 5', 'dateTo' => null],
                        ['id' => 27, 'name' => 'Rule 27', 'dateTo' => null],
                    ],
                    'storeHandle' => 'primary',
                ],
                'storeHandle' => 'primary',
                'missingCount' => 0,
            ]);
        } finally {
            $view->setTemplateMode($originalMode);
        }

        // Selected options (3, 27) must be checked; the unselected one (5) must not be.
        expect($html)
            ->toContain('value="3"')
            ->toContain('value="27"')
            ->toMatch('/value="3"[^>]*checked/')
            ->toMatch('/value="27"[^>]*checked/')
            ->not->toMatch('/value="5"[^>]*checked/');
    });
});

// ---------------------------------------------------------------------------
// Stale-rule warning
//
// When a rule that was ticked earlier has since been deleted in Commerce, the
// input template shows a warning that the stale selection will drop off on the
// next save. These render the partial directly with a supplied option set so
// the assertions don't depend on which rules happen to exist in the store.
// storeHandle is passed as null so the "New Catalog Pricing Rule" button block
// is skipped and no logged-in user is needed.
// ---------------------------------------------------------------------------

describe('PricingRulesRelationshipField stale-rule warning', function() {
    it('warns when a saved rule is no longer in the available options', function() {
        $html = renderPrrInput(
            value: [3, 99],
            sales: [['id' => 3, 'name' => 'Rule 3', 'dateTo' => null]],
            missingCount: 1,
        );

        expect($html)
            ->toContain('class="warning has-icon"')
            ->toContain('no longer available');
    });

    it('shows no warning when every saved rule still exists', function() {
        $html = renderPrrInput(
            value: [3],
            sales: [['id' => 3, 'name' => 'Rule 3', 'dateTo' => null]],
            missingCount: 0,
        );

        expect($html)->not->toContain('no longer available');
    });
});

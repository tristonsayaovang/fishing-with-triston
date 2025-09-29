<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\FunctionalJavascriptTests\SortableTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests with the media library override widget.
 *
 * @group media_library_media_modify
 */
class MediaLibraryWithOverrideWidgetTest extends EntityReferenceOverrideTestBase {

  use MediaTypeCreationTrait;
  use TestFileCreationTrait;
  use SortableTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media',
    'media_library',
  ];

  /**
   * The entity to act on.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The media type of our items.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $mediaType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mediaType = $this->createMediaType('image');

    $this->entity = EntityTest::create();
    $this->entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'administer media',
      'access content',
      'view test entity',
    ]));

    /** @var \StdClass[] $images */
    $images = $this->getTestFiles('image');
    for ($i = 0; $i < 3; $i++) {
      $file = File::create([
        'uri' => $images[$i]->uri,
      ]);
      $file->save();
      $media = Media::create([
        'name' => 'Media ' . $i,
        'bundle' => $this->mediaType->id(),
        'field_media_image' => [
          [
            'target_id' => $file->id(),
            'alt' => 'default alt',
            'title' => 'default title',
          ],
        ],
      ]);
      $media->save();
    }

  }

  /**
   * Test edit form values after item re-order.
   *
   * @dataProvider getWidgetSettings
   */
  public function testEditFormAfterItemReOrder(array $widget_settings): void {
    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'media', $this->mediaType->id(), 'media_library_media_modify_widget', $widget_settings);
    $this->drupalGet($this->entity->toUrl('edit-form'));

    $this->addMediaItems([0, 1, 2]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');

    $this->assertSession()->fieldValueEquals('name[0][value]', 'Media 0', $modal);
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Show media item weights');

    $this->getSession()->getPage()->fillField('field_reference_override[selection][0][_weight]', '1');
    $this->getSession()->getPage()->fillField('field_reference_override[selection][1][_weight]', '0');

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-1"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 2');
    $this->assertSession()->fieldValueEquals('name[0][value]', 'Media 1', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-1"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldValueEquals('name[0][value]', 'Media 1', $modal);
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 2', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog .ui-dialog-titlebar-close')->click();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 12');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-1"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 2', $modal);
  }

  /**
   * Data provider for testEditFormAfterItemReOrder().
   *
   * @return array[]
   *   The provider array.
   */
  public static function getWidgetSettings(): array {
    return [
      [
        [],
      ],
      [
        ['replace_checkbox_by_order_indicator' => TRUE],
      ],
    ];
  }

  /**
   * Test edit form after multiple add items actions.
   *
   * @dataProvider getWidgetSettings
   */
  public function testEditFormAfterMultipleAddItems(array $widget_settings): void {
    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'media', $this->mediaType->id(), 'media_library_media_modify_widget', $widget_settings);
    $this->drupalGet($this->entity->toUrl('edit-form'));

    $this->addMediaItems([0]);
    $this->addMediaItems([1]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the entity.
    $this->getSession()->getPage()->pressButton('Save');
    $this->drupalGet($this->entity->toUrl('edit-form'));
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][0][_weight]', '0');
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][1][_weight]', '1');

    $this->assertSession()->elementExists('css', 'div[data-media-library-item-delta="0"] + div[data-media-library-item-delta="1"]');
    $this->sortableTo('div[data-media-library-item-delta="1"]', 'div[data-media-library-item-delta="1"]', 'div.js-media-library-selection');

    $this->assertSession()->elementExists('css', 'div[data-media-library-item-delta="1"] + div[data-media-library-item-delta="0"]');
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][0][_weight]', '1');
    $this->assertSession()->fieldValueEquals('field_reference_override[selection][1][_weight]', '0');

    // Check the override.
    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Test edit form after multiple add items actions.
   */
  public function testEditFormThenAddAndEditAgain(): void {
    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'media', $this->mediaType->id(), 'media_library_media_modify_widget');
    $this->drupalGet($this->entity->toUrl('edit-form'));

    $this->addMediaItems([0]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->addMediaItems([1]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
  }

  /**
   * Test saving an override and open the edit again.
   */
  public function testOnExistingEntity(): void {
    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'media', $this->mediaType->id(), 'media_library_media_modify_widget');
    $this->drupalGet($this->entity->toUrl('edit-form'));

    $this->addMediaItems([0]);

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Override 1');
    $this->getSession()->getPage()->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->pressButton('Save');

    $this->drupalGet($this->entity->toUrl('edit-form'));

    $this->assertSession()->elementExists('css', '[data-drupal-selector="edit-field-reference-override-selection-0"]')->pressButton('Override media item in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $this->getSession()->getPage()->find('css', '.ui-dialog');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Override 1', $modal);
  }

  /**
   * Selects a number of items from the media library.
   *
   * @param array $indexes
   *   The indexes to select.
   */
  protected function addMediaItems(array $indexes): void {
    $this->getSession()->getPage()->pressButton('Add media');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($indexes as $index) {
      $this->getSession()->getPage()->findAll('css', '.js-media-library-item')[$index]->click();
    }
    $this->assertSession()->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Insert selected');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * {@inheritdoc}
   */
  protected function sortableUpdate($item, $from, $to = NULL): void {
    // See core/modules/media_library/js/media_library.widget.es6.js.
    $script = <<<JS
(function ($) {
    var selection = document.querySelectorAll('.js-media-library-selection');
    selection.forEach(function (widget) {
        $(widget).children().each(function (index, child) {
            $(child).find('.js-media-library-item-weight').val(index);
        });
    });
})(jQuery)

JS;

    $options = [
      'script' => $script,
      'args'   => [],
    ];

    $this->getSession()->getDriver()->getWebDriverSession()->execute($options);
  }

}

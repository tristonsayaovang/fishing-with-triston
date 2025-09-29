<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\FunctionalJavascript;

use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\media_library_media_modify\Traits\AssertAnnounceContainsTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\file\Entity\File;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\FunctionalJavascriptTests\SortableTestTrait;

/**
 * Test the check selected functionality the media library.
 *
 * @group media_library_media_modify
 */
class MediaLibraryMediaModifyCheckSelectedTest extends MediaLibraryTestBase {
  use AssertAnnounceContainsTrait;
  use TestFileCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use SortableTestTrait;

  /**
   * The media item to work with.
   *
   * @var \Drupal\media\MediaInterface[]
   */
  protected $medias = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_library_media_modify',
    'inline_entity_form',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \StdClass[] $images */
    $images = $this->getTestFiles('image');
    $image = File::create([
      'uri' => $images[0]->uri,
    ]);
    $image->setPermanent();
    $image->save();

    $this->medias[1] = Media::create([
      'bundle' => 'type_one',
      'name' => 'Disturbing',
    ]);
    $this->medias[1]->save();

    $this->medias[2] = Media::create([
      'bundle' => 'type_two',
      'name' => 'Disturbing',
    ]);
    $this->medias[2]->save();

    $this->medias[3] = Media::create([
      'bundle' => 'type_three',
      'name' => 'Disturbing',
      'field_media_test_image' => [
        ['target_id' => $image->id()],
      ],
    ]);
    $this->medias[3]->save();

    $this->medias[4] = Media::create([
      'bundle' => 'type_three',
      'name' => 'Disturbing 2',
      'field_media_test_image' => [
        ['target_id' => $image->id()],
      ],
    ]);
    $this->medias[4]->save();

    // Create a user who can use the Media library.
    $user = $this->drupalCreateUser([
      'access content',
      'create basic_page content',
      'edit own basic_page content',
      'view media',
      'create media',
      'administer media',
      'update any media',
      'update media',
      'administer entity_test content',
      'access content',
      'view test entity',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Test check selected for field with cardinality 2.
   */
  public function testCheckSelectedInCardinality2Field(): void {
    $form_display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'basic_page', 'default');
    $form_display->setComponent('field_twin_media', [
      'type' => 'media_library_media_modify_widget',
      'settings' => [
        'check_selected' => TRUE,
      ],
    ])->save();

    $assert_session = $this->assertSession();

    // Visit a node create page.
    $this->drupalGet('node/add/basic_page');

    $this->openMediaLibraryForField('field_twin_media');
    // Select the first item.
    $checkboxes = $this->getCheckboxes();
    $checkboxes[0]->click();
    $this->switchToMediaType('Three');
    $this->assertAnnounceContains('Showing Type Three media.');

    // Select the second item.
    $checkboxes = $this->getCheckboxes();
    $checkboxes[0]->click();

    $this->pressInsertSelected();
    $assert_session->assertWaitOnAjaxRequest();

    // Remove the selected item.
    $wrapper = $assert_session->elementExists('css', '.field--name-field-twin-media');
    $button = $assert_session->buttonExists('Remove', $wrapper);
    $button->press();
    $assert_session->assertWaitOnAjaxRequest();

    $this->openMediaLibraryForField('field_twin_media');
    $this->switchToMediaType('Three');

    // Check item is still selected.
    $assert_session->checkboxChecked("media_library_select_form[3]");
  }

  /**
   * Test check selected for an unlimited cardinality field.
   */
  public function testCheckSelectedInCardinalityUnlimitedField(): void {
    $form_display = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'basic_page', 'default');
    $form_display->setComponent('field_unlimited_media', [
      'type' => 'media_library_media_modify_widget',
      'settings' => [
        'check_selected' => TRUE,
      ],
    ])->save();

    $assert_session = $this->assertSession();

    // Visit a node create page.
    $this->drupalGet('node/add/basic_page');

    $this->openMediaLibraryForField('field_unlimited_media');
    // Select the first item.
    $checkboxes = $this->getCheckboxes();
    $checkboxes[0]->click();
    $this->switchToMediaType('Three');
    $this->assertAnnounceContains('Showing Type Three media.');

    // Select the second item.
    $checkboxes = $this->getCheckboxes();
    $checkboxes[0]->click();

    $this->pressInsertSelected();
    $assert_session->assertWaitOnAjaxRequest();

    $this->openMediaLibraryForField('field_unlimited_media');
    $assert_session->checkboxChecked("media_library_select_form[1]");
    $this->switchToMediaType('Three');

    // Check item is still selected.
    $assert_session->checkboxChecked("media_library_select_form[3]");
    $this->assertSession()->hiddenFieldValueEquals('current_selection', '1,3');

    // Unselect the first item.
    $this->switchToMediaType('One');
    $checkboxes = $this->getCheckboxes();
    $checkboxes[0]->click();

    $this->pressInsertSelected();
    $assert_session->assertWaitOnAjaxRequest();

    $this->assertSession()->elementsCount('css', '.js-media-library-item', 1);
  }

  /**
   * Test that the re-ordering persists during multiple library opens.
   */
  public function testReplaceCheckboxByOrderIndicatorInIef(): void {

    $field_name = 'field_ief';
    $entity_type = 'node';
    $bundle = 'basic_page';
    $selection_handler_settings = [
      'target_bundles' => [
        'entity_test' => 'entity_test',
      ],
    ];
    $this->createEntityReferenceField($entity_type, $bundle, $field_name, $field_name, 'entity_test', 'default', $selection_handler_settings);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'form_mode' => 'default',
        ],
      ])
      ->save();

    $field_name = 'field_media';
    $entity_type = 'entity_test';
    $selection_handler_settings = [
      'target_bundles' => [
        'type_three' => 'type_three',
      ],
    ];
    $this->createEntityReferenceField($entity_type, $entity_type, $field_name, $field_name, 'media', 'default', $selection_handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $display_repository->getFormDisplay($entity_type, $entity_type)
      ->setComponent($field_name, [
        'type' => 'media_library_media_modify_widget',
        'settings' => [
          'check_selected' => TRUE,
          'replace_checkbox_by_order_indicator' => TRUE,
        ],
      ])
      ->save();

    // Visit a node create page.
    $this->drupalGet('node/add/basic_page');

    $this->assertElementExistsAfterWait('css', "#field_media-media-library-wrapper-field_ief-0-inline_entity_form.js-media-library-widget")
      ->pressButton('Add media');
    $this->waitForText('Add or select media');

    foreach ($this->cssSelect('.js-click-to-select-trigger') as $image) {
      $image->click();
    }

    $this->assertSession()->hiddenFieldValueEquals('current_selection', '3,4');
    $this->pressInsertSelected();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->sortableAfter('[data-media-library-item-delta="0"]', '[data-media-library-item-delta="1"]', '#field_media-media-library-wrapper-field_ief-0-inline_entity_form .js-media-library-selection');

    $this->assertElementExistsAfterWait('css', "#field_media-media-library-wrapper-field_ief-0-inline_entity_form.js-media-library-widget")
      ->pressButton('Add media');
    $this->waitForText('Add or select media');
    $this->assertSession()->hiddenFieldValueEquals('current_selection', '4,3');
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

    $this->getSession()->executeScript($script);
  }

}

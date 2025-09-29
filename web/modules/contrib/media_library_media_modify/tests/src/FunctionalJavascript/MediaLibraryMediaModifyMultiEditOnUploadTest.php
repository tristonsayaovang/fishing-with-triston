<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase;
use Drupal\Tests\media_library_media_modify\Traits\AssertAnnounceContainsTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Test the views edit link in the media library.
 *
 * @group media_library_media_modify
 */
class MediaLibraryMediaModifyMultiEditOnUploadTest extends MediaLibraryTestBase {
  use AssertAnnounceContainsTrait;
  use TestFileCreationTrait {
    getTestFiles as drupalGetTestFiles;
  }

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field_ui', 'media_library_media_modify'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
      'administer media fields',
      'administer media form display',
      'administer media display',
      'administer media types',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Test the edit link in media form.
   */
  public function testMultiEdit(): void {
    $display_repository = $this->container->get('entity_display.repository');

    // Add custom field an enable it in 'media_library' form display.
    FieldStorageConfig::create([
      'field_name' => 'field_media_multi_test',
      'entity_type' => 'media',
      'type' => 'text',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'media',
      'field_name' => 'field_media_multi_test',
      'bundle' => 'type_three',
      'label' => 'Test text-field',
    ])->save();
    $display_repository->getFormDisplay('media', 'type_three', 'media_library')
      ->setComponent('field_media_multi_test', [
        'type' => 'text_textfield',
        'weight' => 0,
      ])
      ->save();

    // Multiple media edit forms should show without config.
    $this->drupalGet('node/add/basic_page');
    $this->uploadRandomFiles('field_unlimited_media', 2);

    $this->assertElementExistsAfterWait('css', '[data-drupal-selector="edit-media"]');
    $this->assertElementExistsAfterWait('css', '[data-drupal-selector="edit-media-1-fields-field-media-multi-test-0-value"]');
    $this->assertJsCondition("Array.from(document.querySelectorAll('form'))
      .filter((form) => { return (form.action.indexOf('multi_edit_on_create%5D=1') !== -1) }).length === 0");

    // Enable multi edit.
    $display_repository->getFormDisplay('node', 'basic_page', 'default')
      ->setComponent('field_unlimited_media', [
        'type' => 'media_library_media_modify_widget',
        'settings' => [
          'multi_edit_on_create' => TRUE,
        ],
      ])->save();

    // Dedicated multi edit form should show when configured properly and more
    // than one image is uploaded.
    $this->drupalGet('node/add/basic_page');
    $this->uploadRandomFiles('field_unlimited_media', 2);
    $this->assertJsCondition("Array.from(document.querySelectorAll('form'))
      .filter((form) => { return (form.action.indexOf('multi_edit_on_create%5D=1') !== -1) }).length === 1");
    $this->assertSession()->elementNotExists('css', '[data-drupal-selector="edit-media"]');
    $this->assertElementExistsAfterWait('css', '[data-drupal-selector="edit-field-media-multi-test-0-value"]');

    // Multi edit not enabled when only one file is uploaded.
    $this->drupalGet('node/add/basic_page');
    $this->uploadRandomFiles('field_unlimited_media', 1);
    $this->assertElementExistsAfterWait('css', '[data-drupal-selector="edit-media"]');
    $this->assertElementExistsAfterWait('css', '[data-drupal-selector="edit-media-0-fields-field-media-multi-test-0-value"]');
    $this->assertSession()->elementNotExists('css', '[data-drupal-selector="edit-field-media-multi-test-0-value"]');
  }

  /**
   * Upload files helper.
   *
   * @param string $field_name
   *   Base (media) field name.
   * @param int $count
   *   Number of files.
   */
  private function uploadRandomFiles(string $field_name, int $count = 1): void {
    $this->openMediaLibraryForField($field_name);
    $this->switchToMediaType('Three');
    $this->assertAnnounceContains('Showing Type Three media.');

    $file_system = \Drupal::service('file_system');

    $real_paths = [];
    $remote_paths = [];
    foreach (array_slice($this->drupalGetTestFiles('image'), 0, $count) as $image) {
      $real_paths[] = $file_system->realpath($image->uri);
    }
    /** @var \Drupal\FunctionalJavascriptTests\DrupalSelenium2Driver $driver */
    $driver = $this->getSession()->getDriver();
    foreach ($real_paths as $path) {
      $remote_paths[] = $driver->uploadFileAndGetRemoteFilePath($path);
    }

    $this->addMediaFileToField('Add files', implode("\n", $remote_paths));
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

}

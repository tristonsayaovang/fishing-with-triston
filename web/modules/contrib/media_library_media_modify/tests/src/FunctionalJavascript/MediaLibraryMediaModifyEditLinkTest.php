<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\FunctionalJavascript;

use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media_library\FunctionalJavascript\MediaLibraryTestBase;
use Drupal\Tests\media_library_media_modify\Traits\AssertAnnounceContainsTrait;
use Drupal\views\Views;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\file\Entity\File;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Test the views edit link in the media library.
 *
 * @group media_library_media_modify
 */
class MediaLibraryMediaModifyEditLinkTest extends MediaLibraryTestBase {
  use AssertAnnounceContainsTrait;
  use TestFileCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use TaxonomyTestTrait;

  /**
   * The media item to work with.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media_library_media_modify', 'taxonomy'];

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

    $this->media = Media::create([
      'bundle' => 'type_three',
      'name' => 'Disturbing',
      'field_media_test_image' => [
        ['target_id' => $image->id()],
      ],
    ]);
    $this->media->save();

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
    ]);
    $this->drupalLogin($user);

    $view = Views::getView('media_library');
    $view->setDisplay('widget');

    $fields = $view->displayHandlers->get('widget')->getOption('fields');

    $fields['media_library_media_modify_edit_link'] = [
      'id' => 'media_library_media_modify_edit_link',
      'table' => 'media',
      'field' => 'media_library_media_modify_edit_link',
      'relationship' => 'none',
    ];

    $view->displayHandlers->get('widget')->overrideOption('fields', $fields);
    $view->save();
  }

  /**
   * Test the edit link in media form.
   */
  public function testEditLink(): void {

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Visit a node create page.
    $this->drupalGet('node/add/basic_page');

    $this->openMediaLibraryForField('field_unlimited_media');
    $this->switchToMediaType('Three');
    $this->assertAnnounceContains('Showing Type Three media.');

    // Select the item.
    $checkboxes = $this->getCheckboxes();
    $checkboxes[0]->click();

    // Edit the item.
    $page->pressButton('media_library_media_modify_edit_link-0');
    $assert_session->assertWaitOnAjaxRequest();
    $page->fillField('media[0][fields][field_media_test_image][0][alt]', 'My alt text');
    $this->pressSaveButton();

    // Check item is still selected.
    $assert_session->checkboxChecked("media_library_select_form[1]");

    $media = Media::load($this->media->id());
    $this->assertEquals('My alt text', $media->field_media_test_image->alt);
  }

  /**
   * Test that ajax form elements work in edit form.
   */
  public function testAjaxInEditForm() {
    $vocabulary = $this->createVocabulary();
    $term1 = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary);

    $this->createEntityReferenceField('media', 'type_three', 'field_tags', 'Tags', 'taxonomy_term', 'default', ['target_bundles' => [$vocabulary->id() => $vocabulary->id()]], FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $display_repository->getFormDisplay('media', 'type_three', 'media_library')
      ->setComponent('field_tags')
      ->save();

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Visit a node create page.
    $this->drupalGet('node/add/basic_page');

    $this->openMediaLibraryForField('field_unlimited_media');
    $this->switchToMediaType('Three');
    $this->assertAnnounceContains('Showing Type Three media.');

    // Edit the item.
    $page->pressButton('media_library_media_modify_edit_link-0');
    $assert_session->assertWaitOnAjaxRequest();

    $page->fillField('media[0][fields][field_media_test_image][0][alt]', 'My alt text');
    $page->fillField('media[0][fields][field_tags][0][target_id]', $term1->getName());
    $page->pressButton('Add another item');
    $assert_session->assertWaitOnAjaxRequest();
    $page->fillField('media[0][fields][field_tags][1][target_id]', $term2->getName());
    $this->pressSaveButton();

    $media = Media::load($this->media->id());
    $this->assertEquals([
      ['target_id' => $term1->id()],
      ['target_id' => $term2->id()],
    ], $media->field_tags->getValue());
  }

}

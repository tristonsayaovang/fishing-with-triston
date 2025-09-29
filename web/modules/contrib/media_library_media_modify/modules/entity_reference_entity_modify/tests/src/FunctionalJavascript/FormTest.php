<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_reference_entity_modify\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\media_library_media_modify\FunctionalJavascript\EntityReferenceOverrideTestBase;

/**
 * Form operation tests.
 *
 * @group entity_reference_entity_modify
 */
class FormTest extends EntityReferenceOverrideTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'inline_entity_form',
    'entity_reference_entity_modify',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->markTestIncomplete(
      'This test has not been implemented yet.'
    );
    $this->addReferenceOverrideField('entity_test', 'field_reference_override', 'entity_test_mul', 'entity_test_mul', 'entity_reference_autocomplete_with_override');
  }

  /**
   * Test that overrides persists during multiple modal opens.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testSetOverride(): void {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Original name',
      'field_description' => [
        'value' => 'Original description',
        'format' => 'plain_text',
      ],
    ]);
    $referenced_entity->save();
    $entity = EntityTest::create([
      'field_reference_override' => $referenced_entity,
    ]);
    $entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'access content',
      'view test entity',
    ]));

    $this->drupalGet($entity->toUrl('edit-form'));

    $page = $this->getSession()->getPage();

    // Check that only properties with different values are saved to the hidden
    // field.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->hiddenFieldValueEquals('field_reference_override[0][overwritten_property_map]', '[]');

    // Check that form validation errors are shown.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $page->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', '');
    $page->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '.ui-dialog', 'field_description field is required.');

    // Set a new different value for the description.
    $modal->fillField('field_description[0][value]', 'Overridden description');
    $page->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Open modal again to check if values persist.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Overridden description', $modal);
    $page->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page->pressButton('Save');

    $this->drupalGet($entity->toUrl());

    $this->assertSession()->pageTextContains('Original name');
    $this->assertSession()->pageTextContains('Overridden description');
  }

  /**
   * Test widget in a IEF subform.
   */
  public function testIef(): void {
    $field_name = 'field_ief';
    $entity_type = 'entity_test_rev';
    $selection_handler_settings = [
      'target_bundles' => [
        'entity_test' => 'entity_test',
      ],
    ];
    $this->createEntityReferenceField($entity_type, $entity_type, $field_name, $field_name, 'entity_test', 'default', $selection_handler_settings);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay($entity_type, $entity_type)
      ->setComponent($field_name, [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'form_mode' => 'default',
        ],
      ])
      ->save();

    $referenced_entity = EntityTestMul::create([
      'name' => 'Original name',
      'field_description' => [
        'value' => 'Original description',
        'format' => 'plain_text',
      ],
    ]);
    $referenced_entity->save();
    $entity = EntityTest::create([
      'name' => 'Ief entity',
      'field_reference_override' => $referenced_entity,
    ]);
    $entity->save();

    $main_entity = EntityTestRev::create([
      'field_ief' => $entity,
    ]);
    $main_entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'access content',
      'view test entity',
    ]));

    $this->drupalGet($main_entity->toUrl('edit-form'));

    $page = $this->getSession()->getPage();

    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $page->find('css', '.ui-dialog');
    $modal->fillField('field_description[0][value]', 'Overridden description');
    $page->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Open modal again to check if values persist.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Overridden description', $modal);
    $page->find('css', '.ui-dialog button.form-submit')->click();
  }

  /**
   * Test with not filled required fields on parent form.
   */
  public function testWithFormErrorsOnMainForm(): void {
    $referenced_entity = EntityTestMul::create([
      'name' => 'Original name',
      'field_description' => [
        'value' => 'Original description',
        'format' => 'plain_text',
      ],
    ]);
    $referenced_entity->save();
    $entity = EntityTest::create([
      'name' => '',
      'field_reference_override' => $referenced_entity,
    ]);
    $entity->save();

    $this->drupalLogin($this->drupalCreateUser([
      'administer entity_test content',
      'access content',
      'view test entity',
    ]));

    /** @var \Drupal\Core\Field\FieldConfigInterface $field */
    $field = FieldConfig::loadByName('entity_test', 'entity_test', 'field_test_text');
    $field->setRequired(TRUE);
    $field->save();

    $this->drupalGet($entity->toUrl('edit-form'));

    $page = $this->getSession()->getPage();

    // Check that form validation errors are shown.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $page->find('css', '.ui-dialog');
    $this->assertSession()->elementTextNotContains('css', '.ui-dialog', 'Test text-field field is required.');
    $modal->fillField('field_description[0][value]', 'Overridden description');
    $page->find('css', '.ui-dialog button.form-submit')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Open modal again to check if values persist.
    $page->pressButton('Override test entity - data table in context of this test entity');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextNotContains('css', '.ui-dialog', 'Test text-field field is required.');
    $this->assertSession()
      ->fieldValueEquals('field_description[0][value]', 'Overridden description', $modal);
    $page->find('css', '.ui-dialog button.form-submit')->click();
  }

}

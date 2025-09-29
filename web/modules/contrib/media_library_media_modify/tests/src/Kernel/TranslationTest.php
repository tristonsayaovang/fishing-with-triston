<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Component\Serialization\Json;

/**
 * Testing translation related use cases.
 *
 * @group media_library_media_modify
 */
class TranslationTest extends EntityReferenceOverrideTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_name = 'field_description';
    $entity_type = 'media';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'text_long',
      'entity_type' => $entity_type,
      'cardinality' => 1,
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $this->testMediaType->id(),
      'label' => $field_name,
    ])->save();

    ConfigurableLanguage::create([
      'id' => 'de',
      'label' => 'German',
    ])->save();
  }

  /**
   * Test with translatable parent entity and an untranslatable reference.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTranslatableParentWithUntranslatableReference(): void {
    $referenced_entity = $this->generateMedia('test.txt', $this->testMediaType);
    $referenced_entity->set('field_description', 'Main description');
    $referenced_entity->save();

    // Create english parent entity.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
      ],
      'langcode' => 'en',
    ]);
    $entity->save();

    // Add german translation.
    $entity->addTranslation('de', $entity->toArray());
    $entity->save();

    $entity->field_reference_override->overwritten_property_map = Json::encode([
      'field_description' => "Nice english description!",
    ]);

    $this->assertEquals("Nice english description!", $entity->field_reference_override->entity->field_description->value);

    $translation = $entity->getTranslation('de');
    $this->assertEquals("Main description", $translation->field_reference_override->entity->field_description->value);

    $translation->field_reference_override->overwritten_property_map = Json::encode([
      'field_description' => "Nice german description!",
    ]);
    $translation->save();
    $this->assertEquals("Nice german description!", $translation->field_reference_override->entity->field_description->value);
  }

  /**
   * Test with translatable parent entity and a translatable reference.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testTranslatableParentWithTranslatableReference(): void {
    $referenced_entity = $this->generateMedia('test.txt', $this->testMediaType);
    $referenced_entity->set('field_description', 'Main english description');
    $referenced_entity->set('langcode', 'en');
    $referenced_entity->save();

    $translation = $referenced_entity->addTranslation('de', $referenced_entity->toArray());
    $translation->set('field_description', 'Main german description');
    $translation->save();

    // Create english parent entity.
    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
      ],
      'langcode' => 'en',
    ]);
    $entity->save();

    // Add german translation.
    $entity->addTranslation('de', $entity->toArray());
    $entity->save();

    $entity->field_reference_override->overwritten_property_map = Json::encode([
      'field_description' => "Nice english description!",
    ]);

    $this->assertEquals("Nice english description!", $entity->field_reference_override->entity->field_description->value);

    $translation = $entity->getTranslation('de');
    $this->assertEquals("Main english description", $translation->field_reference_override->entity->field_description->value);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $translation->field_reference_override->entity;
    $referenced_translation = $entity->getTranslation('de');
    $this->assertEquals("Main german description", $referenced_translation->field_description->value);

    $translation->field_reference_override->overwritten_property_map = Json::encode([
      'field_description' => "Nice german description!",
    ]);
    $translation->save();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $translation->field_reference_override->entity;
    $referenced_translation = $entity->getTranslation('de');
    $this->assertEquals("Nice german description!", $referenced_translation->field_description->value);
  }

}

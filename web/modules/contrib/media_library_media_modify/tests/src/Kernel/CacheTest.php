<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Component\Serialization\Json;
use org\bovigo\vfs\vfsStream;

/**
 * Testing caching related use cases.
 *
 * @group media_library_media_modify
 */
class CacheTest extends EntityReferenceOverrideTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $field_name = 'field_reference_override_2';
    $entity_type = 'entity_test';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => 'entity_reference_entity_modify',
      'entity_type' => $entity_type,
      'cardinality' => -1,
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $entity_type,
      'label' => $field_name,
    ])->save();

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
  }

  /**
   * Testing that all expected cache keys exists.
   */
  public function testCacheKeys(): void {
    $referenced_entity = $this->generateMedia('test.txt', $this->testMediaType);
    $referenced_entity->save();

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('entity_test');

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
      ],
      'field_reference_override_2' => [
        [
          'target_id' => $referenced_entity->id(),
        ],
        [
          'target_id' => $referenced_entity->id(),
        ],
      ],
    ]);
    $entity->save();

    $render = $view_builder->view($entity->field_reference_override->entity);
    $this->assertNotContains('entity_reference_entity_modify:', $render['#cache']['keys']);

    $render = $view_builder->view($entity->field_reference_override_2->entity);
    $this->assertNotContains('entity_reference_entity_modify:', $render['#cache']['keys']);

    $entity->field_reference_override->overwritten_property_map = Json::encode([
      'field_description' => 'Overridden description',
    ]);
    $entity->field_reference_override_2->get(1)->overwritten_property_map = Json::encode([
      'field_description' => 'Overridden second description',
    ]);
    $entity->save();

    $render = $view_builder->view($entity->field_reference_override->entity);
    $this->assertContains('entity_reference_entity_modify:entity_test:entity_test:1.field_reference_override.0', $render['#cache']['keys']);

    $render = $view_builder->view($entity->field_reference_override_2->entity);
    $this->assertNotContains('entity_reference_entity_modify:', $render['#cache']['keys']);
    $render = $view_builder->view($entity->field_reference_override_2->get(1)->entity);
    $this->assertContains('entity_reference_entity_modify:entity_test:entity_test:1.field_reference_override_2.1', $render['#cache']['keys']);
  }

  /**
   * Testing that referencing the same entity in multiple fields works.
   */
  public function testReferencingSameEntityInMultipleFields(): void {
    $referenced_entity = $this->generateMedia('test.txt', $this->testMediaType);
    $referenced_entity->set('field_description', 'Description');
    $referenced_entity->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
        'overwritten_property_map' => Json::encode([
          'field_description' => 'Overridden description',
        ]),
      ],
      'field_reference_override_2' => [
        'target_id' => $referenced_entity->id(),
      ],
    ]);

    $entity->save();

    $this->assertEquals("Overridden description", $entity->field_reference_override->entity->field_description->value);
    $this->assertEquals("Description", $entity->field_reference_override_2->entity->field_description->value);
  }

  /**
   * Testing that referencing the same entity in multiple entities works.
   */
  public function testReferencingSameEntityInMultipleEntities(): void {
    $referenced_entity = $this->generateMedia('test.txt', $this->testMediaType);
    $referenced_entity->set('field_description', 'Description');
    $referenced_entity->save();

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('entity_test');
    vfsStream::setup('root');

    $entity1 = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
        'overwritten_property_map' => Json::encode([
          'name' => 'Overridden name',
        ]),
      ],
    ]);
    $entity1->save();

    $build = $view_builder->view($entity1->field_reference_override->entity);

    /** @var \Drupal\Core\Render\Markup $rendered */
    $rendered = \Drupal::service('renderer')->renderRoot($build);
    $this->assertStringContainsString('full | Overridden name', (string) $rendered);

    $entity2 = EntityTest::create([
      'name' => 'Test entity',
      'field_reference_override' => [
        'target_id' => $referenced_entity->id(),
        'overwritten_property_map' => Json::encode([
          'name' => 'Overridden name 2',
        ]),
      ],
    ]);
    $entity2->save();

    $build = $view_builder->view($entity2->field_reference_override->entity);

    /** @var \Drupal\Core\Render\Markup $rendered */
    $rendered = \Drupal::service('renderer')->renderRoot($build);
    $this->assertStringContainsString('full | Overridden name 2', (string) $rendered);
  }

}

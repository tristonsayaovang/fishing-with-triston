<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;

/**
 * Base class for kernel tests.
 */
abstract class EntityReferenceOverrideTestBase extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'filter',
    'entity_test',
    'text',
    'media_library_media_modify',
    'views',
    'media_library',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');

    $field_name = 'field_reference_override';
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
  }

}

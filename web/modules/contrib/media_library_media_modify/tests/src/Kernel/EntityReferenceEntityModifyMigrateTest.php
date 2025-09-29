<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Kernel;

/**
 * Run all parent tests with field migration before.
 *
 * @group media_library_media_modify
 */
class EntityReferenceEntityModifyMigrateTest extends EntityReferenceEntityModifyItemTest {

  /**
   * {@inheritdoc}
   */
  protected $fieldType = 'entity_reference';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\media_library_media_modify\EntityReferenceOverrideService $service */
    $service = \Drupal::service('media_library_media_modify');
    $service->migrateEntityReferenceField('entity_test', 'field_media');
  }

}

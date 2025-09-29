<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Tests for the EntityReferenceOverrideService.
 *
 * @group media_library_media_modify
 */
class EntityReferenceOverrideServiceTest extends EntityReferenceOverrideTestBase {

  /**
   * Test the get overridden values method.
   *
   * @dataProvider getOverriddenValuesProvider
   *
   * @covers \Drupal\media_library_media_modify\EntityReferenceOverrideService::getOverriddenValues
   */
  public function testGetOverriddenValues(string $field_type, array $referenced_entity_value, array $original_entity_value, array $expected): void {

    FieldStorageConfig::create([
      'field_name' => 'field_test',
      'type' => $field_type,
      'entity_type' => 'entity_test',
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'field_test',
    ])->save();

    $referenced_entity = EntityTest::create([
      'name' => 'My name',
      'field_test' => $referenced_entity_value,
    ]);
    $original_entity = EntityTest::create([
      'name' => 'My name',
      'field_test' => $original_entity_value,
    ]);

    /** @var \Drupal\media_library_media_modify\EntityReferenceOverrideService $service */
    $service = \Drupal::service('media_library_media_modify');

    $this->assertEquals($expected, $service->getOverriddenValues($referenced_entity, $original_entity, ['field_test']));
  }

  /**
   * Data provider for testGetOverriddenValues().
   *
   * @return array[]
   *   The provider array.
   */
  public static function getOverriddenValuesProvider(): array {
    return [
      ['string', [['value' => 'foo']], [['value' => 'foo']], []],
      [
        'string',
        [['value' => 'foo1']],
        [['value' => 'foo']],
        ['field_test' => [['value' => 'foo1']]],
      ],
      [
        'image',
        [['target_id' => 1, 'alt' => 'alt', 'button' => 'damn button']],
        [['target_id' => 1, 'alt' => 'alt']],
        [],
      ],
      [
        'image',
        [['target_id' => 1, 'alt' => 'alt override', 'button' => 'damn button']],
        [
          [
            'target_id' => 1,
            'alt' => 'alt',
            'title' => 'title',
            'height' => 2,
            'width' => 2,
          ],
        ],
        ['field_test' => [['alt' => 'alt override']]],
      ],
    ];
  }

}

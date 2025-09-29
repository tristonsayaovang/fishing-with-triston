<?php

declare(strict_types=1);

namespace Drupal\Tests\media_library_media_modify\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\media\Kernel\MediaKernelTestBase;
use Drupal\Component\Serialization\Json;

/**
 * Tests the access of field values with the media item.
 *
 * @group media_library_media_modify
 */
class EntityReferenceEntityModifyItemTest extends MediaKernelTestBase {

  /**
   * Type of the reference field.
   *
   * @var string
   */
  protected $fieldType = 'entity_reference_entity_modify';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
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

    $field_name = 'field_media';
    $entity_type = 'entity_test';
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'type' => $this->fieldType,
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

  /**
   * Tests the overwrites for a media field.
   */
  public function testOverwrittenMetadata(): void {
    $mediaType = $this->createMediaType('file');
    $media = $this->generateMedia('test.patch', $mediaType);
    $media->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_media' => $media,
    ]);
    $entity->save();

    $this->assertEquals('Mr. Jones', $entity->field_media->entity->label());
    $this->assertEquals('', $entity->field_media->entity->field_media_file->entity->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());

    $entity->field_media->overwritten_property_map = Json::encode([
      'name' => 'Overwritten name',
      'field_media_file' => [['description' => 'Nice description!']],
    ]);

    $this->assertEquals('Overwritten name', $entity->field_media->entity->label());
    $this->assertEquals('Nice description!', $entity->field_media->entity->field_media_file->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());

    $entity->save();

    $this->assertEquals('Overwritten name', $entity->field_media->entity->label());
    $this->assertEquals('Nice description!', $entity->field_media->entity->field_media_file->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());
  }

  /**
   * Tests the overwrites for a media field.
   */
  public function testMultivalueOverwrittenMetadata(): void {
    $mediaType = $this->createMediaType('file');

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'type' => 'string',
      'entity_type' => 'media',
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'media',
      'bundle' => $mediaType->id(),
      'label' => 'field_text',
    ])->save();

    $media1 = $this->generateMedia('test.patch', $mediaType);
    $media1->set('field_text', 'Media Text 1');
    $media1->save();

    $media2 = $this->generateMedia('test1.patch', $mediaType);
    $media2->save();

    $entity = EntityTest::create([
      'name' => 'Test entity',
      'field_media' => [$media1, $media2],
    ]);
    $entity->save();

    $this->assertEquals('Mr. Jones', $entity->field_media->entity->label());
    $this->assertEquals('', $entity->field_media->entity->field_media_file->entity->description);
    $this->assertEquals(1, $entity->field_media->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->entity->field_media_file->entity->getFilename());

    $this->assertEquals('Mr. Jones', $entity->field_media->get(1)->entity->getName());
    $this->assertEquals('', $entity->field_media->get(1)->entity->field_media_file->entity->description);
    // ID 3 is correct, ID 2 is the generic media icon.
    $this->assertEquals(3, $entity->field_media->get(1)->entity->field_media_file->entity->id());
    $this->assertEquals('test1.patch', $entity->field_media->get(1)->entity->field_media_file->entity->getFilename());

    $entity->field_media->get(0)->overwritten_property_map = Json::encode([
      'name' => 'Overwritten name',
      'field_media_file' => [['description' => 'Nice description!']],
      'field_text' => [1 => ['value' => 'Overwritten Text 2']],
    ]);
    $entity->field_media->get(1)->overwritten_property_map = Json::encode([
      'name' => 'Overwritten name for media 2',
      'field_media_file' => [['description' => 'Nice description for media 2!']],
    ]);
    $entity->save();

    $this->assertEquals('Overwritten name', $entity->field_media->get(0)->entity->getName());
    $this->assertEquals('Nice description!', $entity->field_media->get(0)->entity->field_media_file->description);
    $this->assertEquals(1, $entity->field_media->get(0)->entity->field_media_file->entity->id());
    $this->assertEquals('test.patch', $entity->field_media->get(0)->entity->field_media_file->entity->getFilename());
    $this->assertEquals('Media Text 1', $entity->field_media->get(0)->entity->field_text->get(0)->value);
    $this->assertEmpty($entity->field_media->get(0)->entity->field_text->get(1));

    $this->assertEquals('Overwritten name for media 2', $entity->field_media->get(1)->entity->getName());
    $this->assertEquals('Nice description for media 2!', $entity->field_media->get(1)->entity->field_media_file->get(0)->description);
    $this->assertEquals(3, $entity->field_media->get(1)->entity->field_media_file->entity->id());
    $this->assertEquals('test1.patch', $entity->field_media->get(1)->entity->field_media_file->entity->getFilename());

    $entity->field_media->get(0)->overwritten_property_map = Json::encode([
      'name' => 'Overwritten name for media 2',
      'field_text' => [],
    ]);
    $entity->save();
    $this->assertEmpty($entity->field_media->get(0)->entity->field_text->get(0));
    $this->assertEmpty($entity->field_media->get(0)->entity->field_text->get(1));
  }

}

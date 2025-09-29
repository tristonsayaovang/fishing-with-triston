<?php

namespace Drupal\media_library_media_modify\Plugin\diff\Field;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\diff\Plugin\diff\Field\EntityReferenceFieldBuilder;

/**
 * Plugin to diff entity reference entity modify fields.
 *
 * @FieldDiffBuilder(
 *   id = "entity_reference_entity_modify_field_diff_builder",
 *   label = @Translation("Entity Reference Entity Modify Field Diff"),
 *   field_types = {
 *     "entity_reference_entity_modify"
 *   },
 * )
 */
class EntityReferenceEntityModifyFieldBuilder extends EntityReferenceFieldBuilder {

  const COMPARE_ENTITY_REFERENCE_ID = 0;
  const COMPARE_ENTITY_REFERENCE_LABEL = 1;

  /**
   * {@inheritdoc}
   */
  public function build(FieldItemListInterface $field_items) {
    $result = [];
    /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
    foreach ($field_items as $field_key => $field_item) {
      if (!$field_item->isEmpty()) {
        $values = $field_item->getValue();
        // Compare entity ids.
        if ($field_item->entity) {
          if ($this->configuration['compare_entity_reference'] === static::COMPARE_ENTITY_REFERENCE_LABEL) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            $entity = $field_item->entity;
            $result[$field_key][] = $entity->label();
          }
          else {
            $result[$field_key][] = $this->t('Entity ID: :id', [
              ':id' => $values['target_id'],
            ]);
          }
          $result[$field_key][] = 'Overwritten property map: ' . $values['overwritten_property_map'];
        }
      }
    }

    return $result;
  }

}

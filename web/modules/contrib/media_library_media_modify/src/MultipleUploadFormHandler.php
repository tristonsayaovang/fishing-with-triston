<?php

namespace Drupal\media_library_media_modify;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Entity\MediaType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Builds a multi edit form for media items.
 */
class MultipleUploadFormHandler implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager) {
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('entity_field.manager'));
  }

  /**
   * Build the multi edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function buildForm(array &$form, FormStateInterface $form_state): void {
    $get = $form_state->get('media');
    // We take the first uploaded media item and build just one form out of it.
    // Since all media items are from the same source, the edit forms of all
    // items are identical.
    $media = reset($get);
    $form_display = static::getFormDisplay($media);
    // If there are no fields to show for a multi edit form, we show the
    // default form.
    if (!$form_display->getComponents()) {
      return;
    }

    // This deserves to be themeable, but it doesn't need to be its own "real"
    // template.
    $form['description'] = [
      '#type' => 'inline_template',
      '#template' => '<p>{{ text }}</p>',
      '#context' => [
        'text' => $this->t('The media items have been created but have not yet been saved. The values from this form will be applied to all new media items.'),
      ],
    ];

    // Hide the original media form.
    $form['media']['#access'] = FALSE;

    $form['media_multiple'] = [];
    $form_display->buildForm($media, $form['media_multiple'], $form_state);

    // @todo Remove as part of https://www.drupal.org/node/2640056
    if (function_exists('field_group_attach_groups')) {
      $context = [
        'entity_type' => $media->getEntityTypeId(),
        'bundle' => $media->bundle(),
        'entity' => $media,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => 'media_library',
      ];

      field_group_attach_groups($form['media_multiple'], $context);
      $form['media_multiple']['#process'][] = [
        '\Drupal\field_group\FormatterHelper',
        'formProcess',
      ];
    }

    // Replace submit and validation callbacks.
    $form['#submit'] = [[static::class, 'submitForm']];
    $form['#validate'] = [[static::class, 'validateForm']];

    $form['actions']['remove_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#limit_validation_errors' => [],
      '#submit' => [[static::class, 'removeButtonSubmit']],
      '#ajax' => [
        'callback' => '::updateFormCallback',
        'wrapper' => 'media-library-wrapper',
        'url' => Url::fromRoute('media_library.ui'),
        'options' => [
          'query' => $form_state->get('media_library_state')->all() + [
            FormBuilderInterface::AJAX_FORM_REQUEST => TRUE,
          ],
        ],
      ],
    ];
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateForm(array &$form, FormStateInterface $form_state): void {
    $added_media = $form_state->get('media') ?: [];
    foreach ($added_media as $media) {
      $form_display = static::getFormDisplay($media);
      $form_display->extractFormValues($media, $form['media_multiple'], $form_state);
      $form_display->validateFormValues($media, $form['media_multiple'], $form_state);
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitForm(array &$form, FormStateInterface $form_state): void {
    $original_form = [];
    $original_form['media'] = [];
    foreach (array_keys($form_state->get('media')) as $delta) {
      $original_form['media'][$delta] = [
        'fields' => $form['media_multiple'],
      ];
    }
    $form_state->getFormObject()->submitForm($original_form, $form_state);
  }

  /**
   * Form submission remove handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function removeButtonSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    foreach (array_keys($form_state->get('media')) as $delta) {
      // Changing the structure of array_parents on the triggering element, so
      // that the original removeButtonSubmit() method will work.
      $triggering_element['#array_parents'] = ['media', $delta, 'remove_button'];
      $form_state->setTriggeringElement($triggering_element);
      /** @var \Drupal\media_library\Form\AddFormBase $form_object */
      $form_object = $form_state->getFormObject();
      $form_object->removeButtonSubmit($form, $form_state);
    }
    $triggering_element['#array_parents'] = $array_parents;
    $form_state->setTriggeringElement($triggering_element);

    // Show a message to the user to confirm the medias are removed.
    \Drupal::messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
    \Drupal::messenger()->addStatus(t('The media items have been removed.'));
  }

  /**
   * Build the edit form for multiple entities.
   *
   * @param \Drupal\media\MediaInterface $media
   *   A media entity.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  public static function getFormDisplay(MediaInterface $media): EntityFormDisplayInterface {
    $media_type = MediaType::load($media->bundle());
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type);

    $form_display = EntityFormDisplay::collectRenderDisplay($media, 'media_library');

    // Remove name and source field from the display, because these fields do
    // not make any sense in a multi edit form.
    $form_display
      ->removeComponent('name')
      ->removeComponent($source_field->getName());

    // Remove fields that are not configurable.
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $definitions = $entity_field_manager->getFieldDefinitions($media->getEntityTypeId(), $media->bundle());
    foreach ($form_display->getComponents() as $name => $component) {
      if (!empty($definitions[$name]) && !$definitions[$name]->isDisplayConfigurable('form')) {
        $form_display->removeComponent($name);
      }
    }
    return $form_display;
  }

}

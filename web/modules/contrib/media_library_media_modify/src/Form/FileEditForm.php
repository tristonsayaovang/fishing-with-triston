<?php

namespace Drupal\media_library_media_modify\Form;

use Drupal\media\MediaInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\file\Element\ManagedFile;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * Creates a form to edit file media entities.
 *
 * @internal
 *   Form classes are internal.
 */
class FileEditForm extends EditForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return parent::getFormId() . '_file';
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityFormElement(MediaInterface $media, array $form, FormStateInterface $form_state): array {
    $element = parent::buildEntityFormElement($media, $form, $form_state);
    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $media->bundle->entity;
    $source_field = $this->getSourceFieldName($media_type);
    if (isset($element['fields'][$source_field])) {
      $element['fields'][$source_field]['widget'][0]['#process'][] = '::processUploadElement';
      $element['fields'][$source_field]['widget'][0]['#process'][] = [
        static::class, 'hideExtraSourceFieldComponents',
      ];
    }
    return $element;
  }

  /**
   * Processes an image or file source field element.
   *
   * @param array $element
   *   The entity form source field element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $form
   *   The complete form.
   *
   * @return array
   *   The processed form element.
   */
  public static function hideExtraSourceFieldComponents(array $element, FormStateInterface $form_state, array $form): array {
    // Remove preview added by ImageWidget::process().
    if (!empty($element['preview'])) {
      $element['preview']['#access'] = FALSE;
    }

    $element['#title_display'] = 'none';
    $element['#description_display'] = 'none';

    // Remove the filename display.
    foreach ($element['#files'] as $file) {
      $element['file_' . $file->id()]['filename']['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * Processes an upload (managed_file) element.
   *
   * @param array $element
   *   The upload element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed upload element.
   */
  public function processUploadElement(array $element, FormStateInterface $form_state): array {
    $element['upload_button']['#ajax']['callback'] = [
      static::class, 'updateFormCallback',
    ];

    $element['remove_button']['#ajax']['callback'] = [
      static::class, 'updateFormCallback',
    ];

    return $element;
  }

  /**
   * AJAX callback to update the entire form based on source field input.
   *
   * @param array $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An AJAX response object.
   */
  public static function updateFormCallback(array &$form, FormStateInterface $form_state, Request $request): AjaxResponse {
    $response = ManagedFile::uploadAjaxCallback($form, $form_state, $request);
    $element = [
      'preview' => [
        '#type' => 'container',
        '#weight' => 10,
        '#attributes' => [
          'class' => [
            'media-library-add-form__preview',
          ],
          'data-drupal-selector' => 'edit-preview',
        ],
      ],
    ];
    if (isset($form["preview"]["#uri"])) {
      $element['preview']['thumbnail'] = [
        '#theme' => 'image_style',
        '#style_name' => 'media_library',
        '#uri' => $form["preview"]["#uri"],
      ];
    }
    $response->addCommand(new ReplaceCommand(".media-library-wrapper [data-drupal-selector='edit-preview']", $element));
    return $response;
  }

}

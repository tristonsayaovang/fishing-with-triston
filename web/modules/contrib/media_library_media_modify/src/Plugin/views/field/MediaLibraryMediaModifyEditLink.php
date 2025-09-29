<?php

namespace Drupal\media_library_media_modify\Plugin\views\field;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\Render\ViewsRenderPipelineMarkup;
use Drupal\views\ResultRow;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\media_library\MediaLibraryState;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SetDialogTitleCommand;

/**
 * Defines a field that outputs a link to open an edit form.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("media_library_media_modify_edit_link")]
class MediaLibraryMediaModifyEditLink extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue(ResultRow $row, $field = NULL): string {
    return '<!--form-item-' . $this->options['id'] . '--' . $row->index . '-->';
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    return ViewsRenderPipelineMarkup::create($this->getValue($values));
  }

  /**
   * Form constructor for the media library select form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function viewsForm(array &$form, FormStateInterface $form_state): void {
    $query = $this->view->getRequest()->query->all();
    $query[FormBuilderInterface::AJAX_FORM_REQUEST] = TRUE;

    foreach ($this->view->result as $row_index => $row) {
      $form[$this->options['id']][$row_index] = [
        '#type' => 'button',
        '#name' => $this->options['id'] . '-' . $row_index,
        '#value' => 'Edit',
        '#limit_validation_errors' => [
          ['current_selection'],
        ],
        '#media' => $this->getEntity($row),
        '#ajax' => [
          'url' => Url::fromRoute('media_library_media_modify.ui'),
          'options' => [
            'query' => $query,
          ],
          // The AJAX system automatically moves focus to the first tabbable
          // element of the modal, so we need to disable refocus on the button.
          'disable-refocus' => TRUE,
          'callback' => [static::class, 'showEditForm'],
          'wrapper' => 'media-library-wrapper',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Show media edit form.'),
          ],
        ],
        '#attributes' => [
          'class' => [
            'media-library-item__edit',
            'icon-link',
          ],
        ],
      ];
    }
  }

  /**
   * Submit handler for the media library edit form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A command to show the edit media form.
   */
  public static function showEditForm(array $form, FormStateInterface $form_state): AjaxResponse {

    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];

    $state = MediaLibraryState::fromRequest(\Drupal::request());

    // This is a workaround to fix
    // https://www.drupal.org/project/drupal/issues/2504115.
    $state->set('media_library_media_modify_edit', $triggering_element['#media']->id());
    \Drupal::request()->query->add(['media_library_media_modify_edit' => $triggering_element['#media']->id()]);
    $form = \Drupal::service('media_library_media_modify.ui_builder')->buildUi($state);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand("#$wrapper_id", $form));
    $response->addCommand(new SetDialogTitleCommand('', 'Edit media'));

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable(): bool {
    return FALSE;
  }

}

<?php

namespace Drupal\media_library_media_modify;

use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\MediaLibraryState;
use Drupal\Core\Form\FormState;

/**
 * A media library UI builder to build the edit form.
 *
 * @phpstan-ignore-next-line
 */
class MediaLibraryMediaModifyUiBuilder extends MediaLibraryUiBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildUi(?MediaLibraryState $state = NULL): array {
    if (!$state) {
      $state = MediaLibraryState::fromRequest($this->request);
    }
    $form = [
      '#theme' => 'media_library_wrapper',
      '#attributes' => [
        'id' => 'media-library-wrapper',
      ],
      // Attach the JavaScript for the media library UI. The number of
      // available slots needs to be added to make sure users can't select
      // more items than allowed.
      '#attached' => [
        'library' => ['media_library/ui'],
        'drupalSettings' => [
          'media_library' => [
            'selection_remaining' => $state->getAvailableSlots(),
          ],
        ],
      ],
    ];

    if ($state->get('media_library_media_modify_edit')) {
      $form['content'] = [
        '#type' => 'container',
        '#theme_wrappers' => [
          'container__media_library_content',
        ],
        '#attributes' => [
          'id' => 'media-library-content',
        ],
        'form' => $this->buildMediaEditForm($state),
      ];
    }
    // When navigating to a media type through the vertical tabs, we only want
    // to load the changed library content. This is not only more efficient, but
    // also provides a more accessible user experience for screen readers.
    elseif ($state->get('media_library_content') === '1') {
      return $this->buildLibraryContent($state);
    }
    else {
      $form['menu'] = $this->buildMediaTypeMenu($state);
      $form['content'] = $this->buildLibraryContent($state);
    }
    return $form;
  }

  /**
   * Get the edit form for the selected media type.
   *
   * @param \Drupal\media_library\MediaLibraryState $state
   *   The current state of the media library, derived from the current request.
   *
   * @return array
   *   The render array for the media type add form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Form\EnforcedResponseException
   * @throws \Drupal\Core\Form\FormAjaxException
   */
  protected function buildMediaEditForm(MediaLibraryState $state): array {
    $media = $this->entityTypeManager->getStorage('media')->load($state->get('media_library_media_modify_edit'));

    if (!$media->access('edit')) {
      return [];
    }

    $selected_type_id = $state->getSelectedTypeId();
    $selected_type = $this->entityTypeManager->getStorage('media_type')->load($selected_type_id);
    $plugin_definition = $selected_type->getSource()->getPluginDefinition();

    if (empty($plugin_definition['forms']['media_library_media_modify_edit'])) {
      return [];
    }

    $form_state = new FormState();
    $form_state->set('media_library_state', $state);

    return $this->formBuilder->buildForm($plugin_definition['forms']['media_library_media_modify_edit'], $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMediaLibraryView(MediaLibraryState $state): array {
    // @todo Make the view configurable in
    //   https://www.drupal.org/project/drupal/issues/2971209
    $view = $this->entityTypeManager->getStorage('view')->load('media_library');
    $view_executable = $this->viewsExecutableFactory->get($view);
    $view_request = $view_executable->getRequest();
    foreach ($view_request->query->keys() as $key) {
      if (strpos($key, 'media_library') !== FALSE) {
        $view_request->query->remove($key);
      }
    }
    $view_executable->setRequest($view_request);
    return parent::buildMediaLibraryView($state);
  }

}

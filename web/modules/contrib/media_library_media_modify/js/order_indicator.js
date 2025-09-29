(($, Drupal, once) => {
  Drupal.MediaLibraryMediaModifyOrderIndicator = Drupal.MediaLibraryMediaModifyOrderIndicator || {};

  /**
   * Update the media library order indicator when loaded or media items are selected.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to select media items.
   */
  Drupal.behaviors.MediaLibraryMediaModifyOrderIndicator = {
    attach(context, settings) {
      const [selection] = once('media-library-order-indicator', '#media-library-modal-selection', context);
      if (selection && settings.media_library_media_modify.replace_checkbox_by_order_indicator) {
        // TODO: Change when media_library moves away from jquery events.
        $(selection).on('change', () => {
          Drupal.MediaLibraryMediaModifyOrderIndicator.addOrderIndicator(context);
        });
        // Creating the indicators when the modal was loaded or the page was changed.
        Drupal.MediaLibraryMediaModifyOrderIndicator.addOrderIndicator(context);
      }
    },
  };

  /**
   * Creates or update the order indicators.
   *
   * @param {Object} context
   *   The current drupal context.
   */
  Drupal.MediaLibraryMediaModifyOrderIndicator.addOrderIndicator = (context) => {
    const form = context.querySelector('.js-media-library-views-form');
    if (!form) {
      return;
    }

    // Remove order indicator for not checked items.
    form.querySelectorAll('.js-media-library-item:not(.checked) .js-order-indicator').forEach((elem) => {
      elem.remove();
    });

    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      // Hide the checkbox.
      input.style.display = 'none';
      const index = Drupal.MediaLibrary.currentSelection.indexOf(input.value);
      if (index >= 0) {
        const wrapper = input.closest('.js-media-library-item');
        const orderIndicator = wrapper.querySelector('.js-order-indicator');
        if (orderIndicator) {
          orderIndicator.innerText = index + 1;
        } else {
          wrapper.insertAdjacentElement('afterbegin', Drupal.theme('mediaLibraryMediaModifyOrderIndicator', index + 1));
        }
      }
    });
  };

  /**
   * Themes an order indicator for the media library items.
   *
   * @param {integer} counter
   *   The current counter.
   *
   * @return {HTMLElement}
   *   A HTMLElement for an order indicator.
   */
  Drupal.theme.mediaLibraryMediaModifyOrderIndicator = (counter) => {
    const orderIndicator = document.createElement('span');
    orderIndicator.classList.add('js-order-indicator', 'media-library-item__order-indicator');
    orderIndicator.innerText = counter;
    return orderIndicator;
  };
})(jQuery, Drupal, once);

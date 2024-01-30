/**
 * @file
 * Defines the behavior of the media entity browser view.
 */

(function ($, Drupal, once) {

  "use strict";

  /**
   * Attaches the behavior of the media entity browser view.
   */
  Drupal.behaviors.mediaEntityBrowserView = {
    attach: function (context) {
      const $view = $(once('media-library-remaining', '.js-media-library-view', context));
      $view
        .find('.js-media-library-item input[type="checkbox"]')
        .on('change', function () {
          const $checkboxes = $view.find('.js-media-library-item input[type="checkbox"]');
          // Only one checkbox can be checked at a time.
          if ($checkboxes.filter(':checked').length === 1) {
            $checkboxes
              .not(':checked')
              .prop('disabled', true)
              .closest('.js-media-library-item')
              .addClass('media-library-item--disabled');
          }
          else {
            $checkboxes
              .prop('disabled', false)
              .closest('.js-media-library-item')
              .removeClass('media-library-item--disabled');
          }
        });
    }
  };

}(jQuery, Drupal, once));

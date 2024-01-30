/**
 * @file
 * Open popup with requested settings.
 */

(function ($, Drupal, once) {

  'use strict';

  /**
   * Behaviors.
   */
  Drupal.behaviors.popupFieldGroup = {
    attach: function (context, settings) {

      $(once('popup-field-group', '.' + settings.popupFieldGroup.linkCssClass, context)).each(function () {
        var link = $(this);
        var targetId = link.data('target');

        if (typeof settings.popupFieldGroup.popups[targetId] !== 'undefined') {
          var popupContent = $('#' + targetId);
          var popupSettings = settings.popupFieldGroup.popups[targetId];

          if (popupContent.length > 0) {
            if (typeof popupSettings.appendTo === "undefined") {
              // Nothing to do.
            }
            else if (popupSettings.appendTo.length > 0) {
              popupSettings.dialog.appendTo = popupSettings.appendTo;
            }
            else {
              // Ensure form elements are not moved outside the form.
              popupSettings.dialog.appendTo = link.parent();
            }

            var dialog = Drupal.dialog(popupContent, popupSettings.dialog);
            popupContent.data('drupalDialog', dialog);

            link.click(function () {
              if (typeof Drupal.behaviors.editor !== 'undefined') {
                Drupal.behaviors.editor.detach(popupContent.get(0), settings, 'move')
              }

              if (popupSettings.modal) {
                dialog.showModal();
              }
              else {
                dialog.show();
              }

              if (typeof Drupal.behaviors.editor !== 'undefined') {
                Drupal.behaviors.editor.attach(popupContent.get(0), settings)
              }
            });

          }
        }

      });
    }
  };

})(jQuery, Drupal, once);

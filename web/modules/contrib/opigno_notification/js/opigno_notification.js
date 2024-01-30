/**
 * @file
 * Contains the functionality for notifications.
 */

(function ($, Drupal, drupalSettings) {

  /**
   * Check for the new notifications and update the marker.
   */
  Drupal.behaviors.opignoNotificationView = {
    attach: function (context, settings) {
      var url = settings.opignoNotifications ? settings.opignoNotifications.updateUrl : undefined;

      // Update messages. Run every minute and refresh.
      if (typeof url !== 'undefined' && !$('body').hasClass('user-messages-auto-check')) {
        setInterval(function () {
          Drupal.ajax({
            type: 'POST',
            url: url,
            async: false,
          }).execute();
        }, 1000 * 60 );
        $('body').addClass('user-messages-auto-check');
      }
    }
  };
}(jQuery, Drupal, drupalSettings));

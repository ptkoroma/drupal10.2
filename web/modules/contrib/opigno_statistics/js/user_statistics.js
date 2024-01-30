/**
 * @file
 * Contains the functionality for user statistics block.
 */

(function ($) {

  /**
   * Refresh the user stats block.
   */
  Drupal.behaviors.refreshUserStatsBlock = {
    attach: function (context, settings) {
      var $select = $('.profile-info #filterRange', context);
      $select.once().on('change', function (e) {
        e.preventDefault();

        Drupal.ajax({
          type: 'POST',
          url: settings.dashboard.userStatsBlockUrl,
          async: false,
          submit: {
            'days': $select.val(),
            'uid': settings.dashboard.userId,
          },
        }).execute();
      });
    }
  };

} (jQuery));

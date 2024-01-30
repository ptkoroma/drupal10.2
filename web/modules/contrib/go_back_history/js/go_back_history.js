(function (Drupal, $, once) {
  'use strict';

  /**
  * Behaviors.
  */
  Drupal.behaviors.goBackHistory = {
    attach: function (context, settings) {
      $(once('goBackHistoryClick', '.go-back-history-btn', context)).each(function () {
        $('.go-back-history-btn').click(function () {
          window.history.back();
        });
      });

    }
  };

}(Drupal, jQuery, once));

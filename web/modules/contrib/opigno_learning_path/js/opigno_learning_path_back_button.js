'use strict';

(function ($, Drupal) {
  Drupal.behaviors.opignoLearningBackButton = {
    attach: function (context, settings) {
      $('.back-btn', context).one("click", function (ev) {
        if ((drupalSettings.learning_path_back_link.js_button || false) && window.history.length > 0) {
          ev.preventDefault();
          window.history.back();
        }
      });
    }
  };
}(jQuery, Drupal));

/* eslint-disable func-names */

(function ($, Drupal) {
  Drupal.behaviors.opignoLearningPathRun = {
    attach: function (context, settings) {
      const $buttom = $('.opigno-quiz-app-course-button');
      if (!$buttom.hasClass('done')) {
        $buttom.once().click();
        $buttom.addClass('done');
        // Clear browser history.
        if (location.href.includes('?')) {
          history.pushState({}, null, location.href.split('?')[0]);
        }
      }
    }
  };
}(jQuery, Drupal));

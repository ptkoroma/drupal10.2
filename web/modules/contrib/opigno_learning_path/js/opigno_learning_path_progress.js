/* eslint-disable func-names */

(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoLearningPathProgress = {
    attach: function (context, settings) {
      $('.progress-ajax-container', context).once('opignoLearningPathProgress').each(function () {
        var $progress = $(this);
        var data = $progress.data();

        var ajaxObject = Drupal.ajax({
          url: drupalSettings.path.baseUrl + 'ajax/progress/build/' + data.groupId + '/' + data.accountId + '/' + data.latestCertDate + '/' + data.class,
          wrapper: $progress.attr('id'),
        });

        ajaxObject.execute();
      });

    },
  };
}(jQuery, Drupal, drupalSettings));

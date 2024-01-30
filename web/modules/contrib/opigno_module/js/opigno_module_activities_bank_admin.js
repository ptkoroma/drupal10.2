(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoModuleActivitiesBankAdmin = {
    attach: function (context, settings) {
      var url = new URL(window.location.href);
      if (url.searchParams.get('page')) {
        $('ul.vertical-tabs__menu > li').removeClass('is-selected');
        $('ul.vertical-tabs__menu > li.last').addClass('is-selected');
        $('.vertical-tabs__pane').css('display', 'none');
        $('.vertical-tabs__pane.activities-bank').css('display', '');
      }
    }
  }
}(jQuery, Drupal, drupalSettings));

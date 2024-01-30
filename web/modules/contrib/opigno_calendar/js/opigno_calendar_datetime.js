(function ($, Drupal) {

  Drupal.behaviors.opignoCalendarDateTime = {

    attach: function (context, settings) {
      var input = $('.daterange-date input', context);
      input.datepicker({
        constrainInput: true,
        firstDay: 1,
        dateFormat: input.attr('data-pattern'),
      });
    },

  };

}(jQuery, Drupal));

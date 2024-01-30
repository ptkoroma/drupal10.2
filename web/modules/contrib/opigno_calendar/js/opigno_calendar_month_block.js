/* eslint-disable */

(function ($, Drupal) {

  Drupal.behaviors.opignoCalendarMonthBlock = {

    attach: function (context, settings) {
      var $container;

      if ($(context).hasClass('view-opigno-calendar')) {
        $container = $(context);
      }
      else {
        $container = $(context).find('.view-opigno-calendar[class*="month"]');
      }

      this.initDayDisplay($container);

      // Check if date is defined.
      if (typeof settings.path.currentQuery !== 'undefined' && settings.path.currentQuery.day !== 'undefined') {
        $container.find('td.date-box[data-day-of-month="' + settings.path.currentQuery.day +'"]:not(.past-month):not(.future-month) .day').click();
        delete settings.path.currentQuery['day'];
      }
      else {
        if ($container.find('td.date-box.today .day').length) {
          $container.find('td.date-box.today .day').click();
        }
        else {
          $container.find('td.date-box[data-day-of-month="1"]:not(.past-month):not(.future-month) .day').click();
        }
      }

      var $today = $(context).find('#today');

      $today.click(function() {
        // check if current month is opened.
        if ($container.find('td.date-box.today .day').length == 0) {
          window.location = settings.path.baseUrl + settings.path.pathPrefix + 'opigno/calendar';
        }

        $container.find('td.date-box.today .day').click();
      });
    },

    initDayDisplay: function ($container) {
      $container.find('td.date-box .day').click(function () {
        var activeClassName = 'single-day-active',
          $previousActive =  $container.find('.' + activeClassName),
          $dateBox = $(this).closest('td.date-box'),
          date = $dateBox.attr('date-date'),
          $newActive = $container.find('td.single-day[date-date="' + date + '"]');

        if (!$newActive.is($previousActive)) {
          $container.find('td.date-box').removeClass('selected-date');
          $newActive.addClass(activeClassName);
          $container.addClass(activeClassName);
          $previousActive.removeClass(activeClassName);
        }

        if (
          !$dateBox.hasClass('no-entry') &&
          !$dateBox.hasClass('past-month') &&
          !$dateBox.hasClass('future-month')
        ) {
          $dateBox.addClass('selected-date');
        }

        return false;
      });
    }
  };

}(jQuery, Drupal));

/**
 * @file
 * Contains the functionality for the "read more"/"show less" link.
 */

(function ($) {

  /**
   * Read more/show less link functionality.
   */
  Drupal.behaviors.readMore = {
    attach: function (context, settings) {
      $('.opigno-read-more', context).on('click', function (e) {
        e.preventDefault();
        var $summary = $(this).closest('.summary');
        var $text = $summary.next('.full-text');
        $summary.hide();
        $text.slideDown(500);
      })

      $('.opigno-show-less', context).on('click', function (e) {
        e.preventDefault();
        var $text = $(this).closest('.full-text');
        var $summary = $text.prev('.summary');
        $text.slideUp(500);
        $summary.slideDown(500);
      })
    }
  };

}(jQuery));

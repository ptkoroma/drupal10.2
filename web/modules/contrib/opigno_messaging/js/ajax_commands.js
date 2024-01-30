/**
 * @file
 * Contains functionality for additional private message AJAX commands.
 */

(function ($) {

  /**
   * Define the AJAX command to scroll to the last message.
   */
  Drupal.AjaxCommands.prototype.opignoScrollToLastMessage = function () {
    $('.private-message-thread-messages').animate({
      scrollTop: $('.opigno-messages-scroll-target').offset().top
    }, 1000);
};

}(jQuery));

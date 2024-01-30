/**
 * @file
 * Contains the functionality for posts and comments.
 */

(function ($) {

  /**
   * Create the comment.
   */
  Drupal.behaviors.createComment = {
    attach: function (context) {
      $('input.opigno-create-comment', context).once().on('click', function (e) {
        e.preventDefault();
        var pid = $(this).attr('data-opigno-post');
        var $textfield = $('#opigno-comment-text-' + pid, context);

        Drupal.ajax({
          type: 'POST',
          url: $(this).attr('data-ajax-url'),
          async: true,
          submit: {
            'text': $textfield.val(),
          },
        }).execute();
      });
    }
  };

  /**
   * Ajax request to check if new posts were created.
   */
  Drupal.behaviors.checkNewPosts = {
    attach: function (context) {
      var $link = $('#opigno-new-posts-link', context);
      var $url = $link.attr('data-opigno-social-check-posts-url');

      if (typeof $url === 'undefined') {
        return;
      }

      // Send the ajax request every minute to check if new posts were created.
      setInterval(function () {
        var $wrapper = $('.btn-new-post__wrapper', context);
        if ($wrapper.hasClass('hidden')) {
          $.ajax({
            type: 'POST',
            url: $url,
            async: true,
            success: function (data) {
              if (data.newPosts === true) {
                $wrapper.removeClass('hidden');
              }
            }
          })
        }
      }, 60000);
    }
  }

}(jQuery));

/**
 * @file
 * Contains the functionality for posts sharing.
 */

(function ($) {

  /**
   * Open the popup with the shareable post content.
   */
  Drupal.behaviors.sharePostContent = {
    attach: function (context, settings) {
      var $url = settings.opignoSocial ? settings.opignoSocial.shareContentUrl : undefined;
      if (typeof $url === 'undefined') {
        return;
      }

      // Call the popup opening when the user is clicking on the line in the
      // "sharable content" block.
      $('a[data-opigno-post-attachment-id]').once().on('click', function (e) {
        e.preventDefault();
        var data = {
          'type': $(this).attr('data-opigno-attachment-type'),
          'id': $(this).attr('data-opigno-post-attachment-id'),
          'entity_type': $(this).attr('data-opigno-attachment-entity-type'),
          'text': $('#create-post-textfield').val(),
        };

        Drupal.ajax({
          type: 'POST',
          url: $url,
          async: true,
          submit: data,
        }).execute();
      });
    }
  };

  /**
   * Create the post with the attachment.
   */
  Drupal.behaviors.createPostWithAttachment = {
    attach: function (context) {
      $('input.opigno-create-shared-post', context).once().on('click', function (e) {
        e.preventDefault();
        var $textfield = $(this).hasClass('main-wall') ? $('#create-post-textfield') : $('#create-share-post-textfield');
        $textfield.on('focus', function () {
          $(this).removeClass('error');
        })

        Drupal.ajax({
          type: 'POST',
          url: $(this).attr('data-ajax-url'),
          async: true,
          submit: {
            'text': $textfield.val(),
            'type': $(this).attr('data-opigno-attachment-type'),
            'id': $(this).attr('data-opigno-post-attachment-id'),
            'entity_type': $(this).attr('data-opigno-attachment-entity-type'),
          },
        }).execute();
      });
    }
  };

}(jQuery));

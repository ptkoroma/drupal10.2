(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoModuleActivity = {
    attach: function (context, settings) {
      var that = this;
      var fullScreen = {
        show: function () {
          $('body').addClass('fullscreen');
          // Cookies.set('fullscreen', 1);
          that.goInFullscreen(document.querySelector('html'));
        },
        hide: function () {
          $('body').removeClass('fullscreen');
          // Cookies.set('fullscreen', 0);
          that.goOutFullscreen();
        }
      };

      $('.fullscreen-link a', context).once('opignoModuleActivity').on('click', function(e) {
        e.preventDefault();

        if ($('body').hasClass('fullscreen')) {
          fullScreen.hide();
        }
        else {
         fullScreen.show();
        }
      });

      $(document, context).on('webkitfullscreenchange mozfullscreenchange fullscreenchange MSFullscreenChange', function(e) {
        if (!document.fullscreenElement && !document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
          fullScreen.hide();
        }
      });

      var activityDeleteForm = $('form.opigno-activity-with-answers');
      if (activityDeleteForm.length) {
        activityDeleteForm.submit();
      }
    },
    goInFullscreen: function (element) {
      if (element.requestFullscreen) {
        element.requestFullscreen();
      }
      else if (element.mozRequestFullScreen) {
        element.mozRequestFullScreen();
      }
      else if (element.webkitRequestFullscreen) {
        element.webkitRequestFullscreen();
      }
      else if (element.msRequestFullscreen) {
        element.msRequestFullscreen();
      }
    },

    goOutFullscreen: function () {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      }
      else if (document.mozCancelFullScreen) {
        document.mozCancelFullScreen();
      }
      else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
      else if (document.msExitFullscreen) {
        document.msExitFullscreen();
      }
    },
  };
}(jQuery, Drupal, drupalSettings));

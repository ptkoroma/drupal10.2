import 'popper.js'
import 'bootstrap'
import 'bootstrap-select'

(function($, Drupal, drupalSettings) {
  'use strict';

  $.resizeAction = function(conditionCallback, callback) {
    var ready = true;

    $(window).on('resize', function() {
      if (conditionCallback.call(this)) {
        if (ready) {
          callback.call(this, ready);
          ready = !ready;
        }
      }
      else {
        if (!ready) {
          callback.call(this, ready);
          ready = !ready;
        }
      }
    });
  };

  Drupal.behaviors.toggleGrid = {
    attach: function(context, settings) {
      $('.view-style a.style-btn', context).click(function (e) {
        e.preventDefault();
        if ($(this).hasClass('line')) {
          $(this).closest('.view').addClass('style-line');
        } else {
          $(this).closest('.view').removeClass('style-line');
        }
      });
    }
  };

  Drupal.behaviors.mobileMenu = {
    attach: function (context, settings) {
      $('.mobile-menu-btn', context).on('click', function() {
        $('body', context).toggleClass('menu-open');
        $('.mobile-header-wrapper').slideToggle();
      });
    }
  };


  Drupal.behaviors.toggleFeed = {
    attach: function (context, settings) {
      $('.feed-link', context).on('click', function() {
        $('body', context).addClass('open-feed');
        window.scrollTo({ top: 0 });
      });
    }
  };

  Drupal.behaviors.toggleGrid = {
    attach: function (context, settings) {
      const $body = $('body', context);

      $('.show-filter', context).on('click', function(e) {
        e.preventDefault();
        $body.addClass('open-filter');
        window.scrollTo({ top: 0 });
        $('.catalog-filter').fadeIn();
      });

      $('.close-btn', context).on('click', function() {
        if ($body.hasClass('open-filter')) {
          $('.catalog-filter').fadeOut();
        }

        $body.removeClass('open-feed open-filter');
      });
    }
  };

  // Close the filters clicking on "Apply" button on mobile.
  Drupal.behaviors.catalogFilters = {
    attach: function (context) {
      $('.apply-catalog-filters', context).once().on('click', function () {
        var $backLink = $('#catalog-filter-parent .close-btn:visible');
        if ($backLink.length !== 0) {
          $backLink.click();
        }
      });
    }
  }

  Drupal.behaviors.socialFeed = {
    attach: function(context, settings) {
      const $body = $('body');

      if ($(context).hasClass('dashboard')) {
        const $dashboard = $(context);

        $dashboard.on('click', '.feed-link', function() {
          $body.addClass('open-feed');
          window.scrollTo({ top: 0 });
        });

        $dashboard.on('click', '.close-btn', function() {
          $body.removeClass('open-feed');
        });
      }
    }
  };

  Drupal.behaviors.activitiesListDropdown = {
    attach: function(context, settings) {
      $('.opigno-lp-step-module', context).on('click', '.module-title', function() {
        const $moduleTitle = $(this);
        $moduleTitle.toggleClass('open');
        $moduleTitle.siblings('.passed-activities').slideToggle();
        $moduleTitle.siblings('.activities-list').slideToggle();
      });

      $('.show-activity-list', context).on('click', function() {
        if (window.innerWidth <= 991) {
          $(this).fadeOut(function() {
            $(this).siblings('.opigno-lp-step-activity', context).fadeIn();
          });
          $('.lp_progress_wrapper, .opigno_activity__wrapper', context).fadeOut();
        }
      });
    }
  };

  Drupal.behaviors.expandInnerTable = {
    attach: function(context, settings) {
      $('.details-btn', context).once().on('click', function(e) {
        e.preventDefault();

        $(this).toggleClass('active').closest('tr').next().find('.inner-table').slideToggle();
      });
    }
  };

  Drupal.behaviors.frontpageSlider = {
    attach: function (context, settings) {
      if ($('body.anonymous-slider', context).length) {
        let nbSlides = 0;
        let currentSlide = 0;

        $('.anonymous-slider .slider-item', context).not(`:eq(${nbSlides})`)
          .addClass('hide')
          .hide();
        $('.anonymous-slider .slider-item', context).each(function () {
          $(this).attr('data-id', nbSlides);
          nbSlides += 1;
        });
        setInterval(() => {
          currentSlide = (currentSlide < nbSlides - 1) ? currentSlide += 1 : currentSlide = 0;
          $(`.anonymous-slider .slider-item:not([data-id="${currentSlide}"])`)
            .addClass('hide')
            .fadeOut('slow');
          $(`.anonymous-slider .slider-item[data-id="${currentSlide}"]`)
            .removeClass('hide')
            .fadeIn('slow');
        }, 2000);
      }
    }
  };

  Drupal.behaviors.anonymousUserForms = {
    attach: function (context, settings) {
      if (!$('body.anonymous-slider').length) {
        return;
      }

      $('#user-sidebar .switch-link a', context).once().click(function (e) {
        const href = $(this).attr('href');
        if ($(`.form-wrapper[data-target="${href}"]`, context).length) {
          e.preventDefault();
          $('.form-wrapper[data-target]', context)
            .not((`.form-wrapper[data-target="${href}"]`))
            .hide();
          $(`.form-wrapper[data-target="${href}"]`, context).show();
        }
      });
    }
  };

  Drupal.behaviors.messges = {
    attach: function(context, settings) {
      const $messageThread = $('.private-message-thread-messages', context);

      if (context === document) {
        $messageThread.scrollTop($messageThread.prop('scrollHeight'));
      }

      $('.show-message-list', context).on('click', function(e) {
        e.preventDefault();

        $('body', context).toggleClass('open-message-list');
      });
    }
  };

  Drupal.behaviors.activateSelectpicker = {
    attach: function(context) {
      $('.selectpicker', context).each(function () {
        if (!$(this).hasClass('processed')) {
          $(this).selectpicker();
          $(this).addClass('processed')
        }
      });
    }
  };

  Drupal.behaviors.yourCommunities = {
    attach: function(context, settings) {
      $('.your-community', context).on('click', '.content-box__title', function() {
        if (window.innerWidth < 1200) {
          $(this).toggleClass('expanded').next('.communities-list').slideToggle();
        }
      })
    }
  }

  $.resizeAction(function() {
    return window.innerWidth > 991
  }, function(isTrue) {
    if (isTrue) {
      $('body').removeClass('open-filter menu-open open-message-list');
      $('.mobile-header-wrapper, .catalog-filter, .opigno-lp-step-activity, .lp_progress_wrapper, .opigno_activity__wrapper').removeAttr('style');
    }
  });

  $.resizeAction(function() {
    return window.innerWidth >= 1200
  }, function(isTrue) {
    if (isTrue) {
      $('.your-community .content-box__title').removeClass('expanded');
      $('.communities-list').removeAttr('style');
    }
  });
}(jQuery, Drupal, drupalSettings));

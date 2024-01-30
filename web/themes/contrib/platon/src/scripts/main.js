/* eslint func-names: ["error", "never"] */

import 'bootstrap';
import 'iframe-resizer';

/**
 * All Drupal Platon JavaScript APIs are contained in this namespace.
 *
 * @namespace
 */
(function ($, Drupal, drupalSettings) {
  Drupal.platon = {
    settings: drupalSettings.platon || {
      homepageSlideSpeed: 2000,
    },

    getViewport() {
      return {
        width: Math.max(document.documentElement.clientWidth, window.innerWidth || 0),
        height: Math.max(document.documentElement.clientHeight, window.innerHeight || 0),
      };
    },

    frontpageSlider(context) {
      if ($('body.anonymous-slider', context).length) {
        let nbSlides = 0;
        let currentSlide = 0;

        $('.anonymous-slider .slider-item').each(function () {
          $(this).attr('data-id', nbSlides);
          if (nbSlides) {
            $(this)
              .addClass('hide')
              .fadeOut();
          }
          nbSlides += 1;
        });

        setInterval(() => {
          if (currentSlide < nbSlides - 1) {
            currentSlide += 1;
          }
          else {
            currentSlide = 0;
          }

          $(`.anonymous-slider .slider-item:not([data-id="${currentSlide}"])`)
            .addClass('hide')
            .fadeOut();
          $(`.anonymous-slider .slider-item[data-id="${currentSlide}"]`)
            .removeClass('hide')
            .fadeIn();
        }, Drupal.platon.settings.homepageSlideSpeed);
      }
    },

    anonymousUserForms(context) {
      if (!$('body.anonymous-slider').length) {
        return;
      }

      $('#user-sidebar a', context).once().click(function (e) {
        const href = $(this).attr('href');
        if ($(`.form-wrapper[data-target="${href}"]`).length) {
          e.preventDefault();
          $('.form-wrapper[data-target]').hide();
          $(`.form-wrapper[data-target="${href}"]`).show();
        }
      });
    },
    searchBar(context) {
      let $toggleButton = $('.search-trigger #search-trigger', context);
      let $searchForm = $('#search-form', context);
      let $searchInput = $searchForm.find('input[type="search"]');

      $toggleButton.once('searchBar').on('click.searchBar', function (e) {
        if ($toggleButton.is('.open')) {
          $toggleButton.removeClass('open');
          $searchForm.hide();
        }
        else {
          $toggleButton.addClass('open');
          $searchForm.show();
          $searchInput.focus();
        }

        e.preventDefault();
      });

      $searchInput.on('blur.searchBar', function () {
        $toggleButton.trigger('click.searchBar');
      });
    },
    trainingCatalog(context) {
      $('body.page-catalogue .views-exposed-form').find('fieldset#edit-sort-by--2--wrapper legend, fieldset#edit-sort-by--wrapper legend', context).once().click(function () {
        if ($(this).hasClass('active')) {
          $(this).removeClass('active');
        }
        else {
          $(this).addClass('active');
        }
      });
    },

    privateMessageRecipients(context) {
      const pmc = $('.private-message-recipients', context);

      pmc.each(function () {
        const lh = parseInt($(this).css('line-height'), 10);
        $(this).css('max-height', lh);

        if ($(this).find('.content').height() > lh) {
          $(this).css('max-height', lh).addClass('short').append('<a href="#" class="expander">...</a>');
          $(this).find('.expander').on('click', function () {
            $(this).closest('.private-message-recipients').css('max-height', 'none').removeClass('short');
            $(this).hide();
            return false;
          });
        }
      });
    },

    stepsVisibility(context) {
      if (!$('div#block-lp-steps-block', context).length) {
        return;
      }

      const $btn = $('<a href="#" id="lp-steps-trigger" class="btn btn-link mr-auto"><i class="icon-module-open"></i>show</a>');
      const $content = $('#content', context);
      const $sidebar = $('#sidebar-first', context);
      const defaultMainClass = $('#content', context).attr('class');
      const opignoTrainingSession = {
        get: function () {
          let opignoTraining = JSON.parse(window.sessionStorage.getItem('Opigno.training'));

          if (opignoTraining === null) {
            opignoTraining = {};
            opignoTraining.showSidebar = false;
          }

          return opignoTraining;
        },
        set: function (opignoTraining) {
          window.sessionStorage.setItem('Opigno.training', JSON.stringify(opignoTraining));
        },
        toggleSidebarState: function () {
          let opignoTraining = this.get();
          opignoTraining.showSidebar = !opignoTraining.showSidebar;
          this.set(opignoTraining);
        }
      };

      $sidebar.hide();
      $content.addClass('col-lg-12');

      // Add trigger
      $('#main div#edit-actions', context).prepend($btn);

      // Handle trigger clicks
      $btn.once().click((e) => {
        e.preventDefault();
        opignoTrainingSession.toggleSidebarState();

        if ($btn.hasClass('open')) {
          $btn.removeClass('open');
          $sidebar.hide();
          $content.addClass('col-lg-12');
        }
        else {
          $btn.addClass('open');
          $sidebar.show();
          $content.attr('class', defaultMainClass);
        }

        $(window).trigger('resize');
        if (typeof H5P !== 'undefined') {
          H5P.jQuery(window).trigger('resize');
        }
      });

      // Show sidebar if it was open before.
      if (opignoTrainingSession.get().showSidebar && this.getUrlParameter('skip-links') !== '1') {
        $btn.addClass('open');
        $sidebar.show();
        $content.attr('class', defaultMainClass);
      }

      // Hide breadcrumbs.
      if (this.getUrlParameter('skip-links') === '1') {
        $('.block-system_breadcrumb_block').hide();
      }
    },
    getUrlParameter(name) {
      name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
      var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
      var results = regex.exec(location.search);
      return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    },
    formatTFTOperations(context) {
      const $td = $('div#documents-library table tbody tr > td:last-child', context);

      $td.each(function () {
        if ($(this).hasClass('js-formatted')) {
          return;
        }

        $(this).addClass('js-formatted');

        let html = '<div class="btn-group operations">';
        html += '<button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown"></button>';
        html += '<div class="dropdown-menu dropdown-menu-right">';
        $(this).find('a').each(function () {
          $(this).removeClass('ops-link');
          html += $(this)[0].outerHTML;
        });
        html += '</div>';
        html += '</div>';

        $(this).html(html);
      });
    },

    mobileMenu(context) {
      const $toggler = $('button.navbar-toggler', context);
      const $nav = $('div#menu-wrapper', context);

      $toggler.once().click(() => {
        if ($toggler.hasClass('open')) {
          $toggler.removeClass('open');
          $nav.removeClass('show');
        }
        else {
          $toggler.addClass('open');
          $nav.addClass('show');
        }
      });
    },

    fileWidget(context) {
      $('.opigno-file-widget-wrapper', context).each(function () {
        if ($(this).find('input[type="hidden"] + span.file').length) {
          $(this).addClass('not-empty');
        }
        else {
          $(this).removeClass('not-empty');
        }
      });
    },

    packageFileInput(context) {
      $(document, context).on('change', 'input[name="files[package]"]', function (e) {
        const $label = $(this).next('.description');
        const fileName = e.target.value.split('\\').pop();

        if (!$label.data('default-value')) {
          $label.data('default-value', $label.text());
        }

        if (fileName) {
          $label.text(fileName);
        }
        else {
          $label.text($label.data('default-value'));
        }
      });
    },

    viewOpignoActivitiesBank(context) {
      if ($('.view-opigno-activities-bank-lp-interface', context).length) {
        let $pager = $('nav[aria-labelledby="pagination-heading"]', context);
        $pager.children('ul.pager').css('margin-bottom', '-1rem');
        $('form#views-form-opigno-activities-bank-lp-interface-default > table', context).after($pager);
      }
    },

    /**
     * Trigger click. Use for fake buttons.
     *
     * @param context
     */
    triggerClick(context) {
      $('[data-toggle="trigger_click"]', context).each(function () {
        let $this = $(this);
        let $target = $($this.data().target);

        $this.on('click', function (e) {
          if ($target.length) {
            $target.trigger('click');

            e.preventDefault();
          }
        })
      })
    },

    /**
     * Simple loader.
     *
     * @param context
     */
    pageLoader(context) {
      // Move to execution.
      setTimeout(function () {
        $('body', context).addClass('page-ready');
      });
    },
    ajaxFullScreenLoader: {
      show: function () {
        var $spinner = $('<div id="ajaxFullScreenLoader" class="spinner-overlay">' +
          '<div class="spinner" role="status"><span class="sr-only">Loading...</span>\n' +
          '</div>');

        if (!$('#ajaxFullScreenLoader').length) {
          $('body').append($spinner);
          $spinner.fadeIn('fast');
        }
      },
      hide: function () {
        var $spinner = $('#ajaxFullScreenLoader');
        $spinner.fadeOut('fast', function () {
          $spinner.remove();
        })
      }
    },
    videoPreview: function (context) {
      $('.file-video-wrapper', context).once('videoPreview').each(function () {
        var $wrapper = $(this);
        var $img = $wrapper.find('.video-image-preview');

        if ($img.length) {
          var $video = $wrapper.find('video');

          $img.one('click', function () {
            $img.hide();
            $video.show();
            $video[0].play();
          });
        }
      });
    },
    /**
     * Display duplicate content in different screen to avoid errors from accessibility and SEO.
     * @param context
     */
    copyContent: function (context) {
      $('[data-copy-content]', context).once().each(function () {
        var $this = $(this);
        var $copyContent = $($this.data().copyContent);

        if ($copyContent.length) {
          $copyContent = $copyContent.clone(true, true);

          $copyContent.find('[id]').each(function () {
            var $this = $(this);
            $this.attr('id', $this.attr('id') + '_clone');
          });

          $this.prepend($copyContent.children());
        }
      });
    }
  };

  Drupal.behaviors.platon = {
    attach(context) {
      Drupal.platon.frontpageSlider(context);
      Drupal.platon.anonymousUserForms(context);
      Drupal.platon.searchBar(context);
      Drupal.platon.trainingCatalog(context);
      Drupal.platon.privateMessageRecipients(context);
      Drupal.platon.stepsVisibility(context);
      Drupal.platon.mobileMenu(context);
      Drupal.platon.fileWidget(context);
      Drupal.platon.packageFileInput(context);
      Drupal.platon.viewOpignoActivitiesBank(context);
      Drupal.platon.triggerClick(context);
      Drupal.platon.pageLoader(context);
      Drupal.platon.videoPreview(context);
      Drupal.platon.copyContent(context);

      // Temp class to calc iframe height.
      $('#training-content-wrapper iframe', context).parents('.tab-pane').addClass('adapt-iframe-size');

      $('iframe').iFrameResize({
        warningTimeout: 10000,
        resizedCallback: function (event) {
          if (+event.height) {
            $(event.iframe).parent().height(+event.height);
          }

          // Remove temp class.
          $(event.iframe).parents('.adapt-iframe-size').removeClass('adapt-iframe-size');
        }
      });

      $('a[href="#documents-library"]', context).once().click(() => {
        Drupal.platon.formatTFTOperations(context);
      });

      $(document).ajaxSuccess(() => {
        Drupal.platon.formatTFTOperations(context);
      });
    },
  };
}(window.jQuery, window.Drupal, window.drupalSettings));

(function ($, Drupal) {
  Drupal.behaviors.opignoLearningPathAchievements = {
    attach: function (context) {
      var $detailsShow = $('.lp_details_show', context);
      var $detailsHide = $('.lp_details_hide', context);
      var $lpStepTitleWrapper = $('.lp_step_title_wrapper', context);

      $lpStepTitleWrapper.once('click').click(function () {
        if ($(this).hasClass('open')) {
          $(this)
            .removeClass('open')
            .next('.lp_step_content')
            .hide();
        }
        else {
          $(this)
            .addClass('open')
            .next('.lp_step_content')
            .show();
        }
      });

      $detailsShow.once('click').click(function (e) {
        e.preventDefault();

        var $this = $(this);
        var $parent = $this.parent('.lp_wrapper');

        if (!$parent) {
          return false;
        }

        var $details = $parent.find('.lp_details[data-ajax-loaded]');
        if ($details.length === 0) {
          if (!$this.attr('data-ajax-loading')) {
            var training = $this.attr('data-training');
            $this.attr('data-ajax-loading', true);
            Drupal.ajax({
              url: 'ajax/achievements/training-steps/' + training,
            }).execute()
                .done(function () {
                  $parent.find('.lp_details').show();
                  $parent.find('.lp_details_show').hide();
                  $parent.find('.lp_details_hide').show();
                })
                .always(function () {
                  $this.removeAttr('data-ajax-loading');
                });
          }
        }
        else {
          $details.show();
          $parent.find('.lp_details_show').hide();
          $parent.find('.lp_details_hide').show();
        }

        return false;
      });

      $detailsHide.once('click').click(function (e) {
        e.preventDefault();

        var $parent = $(this).parents('.lp_wrapper');

        if (!$parent) {
          return false;
        }

        var $details = $parent.find('.lp_details');
        var height = $details.height();

        $details.hide();
        $parent.find('.lp_details_show').show();
        $parent.find('.lp_details_hide').hide();

        window.scrollBy(0, -height);

        return false;
      });

      var $moduleRow = $('.lp_course_steps tr td:nth-child(4) a', context);
      $moduleRow.once('click').click(function (e) {
        e.preventDefault();

        var $panels = $('.lp_module_panel', context);
        $panels.hide();

        var $this = $(this);
        var $wrapper = $this.parents('.lp_course_steps_wrapper');
        var training = $this.closest('tr').attr('data-training');
        var course = $this.closest('tr').attr('data-course');
        var module = $this.closest('tr').attr('data-module');
        var panelSelector = '#module_panel_' + training + '_' + course + '_' + module + '[data-ajax-loaded]';
        var $panel = $wrapper.find(panelSelector);
        if ($panel.length === 0) {
          if (!$this.closest('tr').attr('data-ajax-loading')) {
            $this.closest('tr').attr('data-ajax-loading', true);
            Drupal.ajax({
              url: 'ajax/achievements/module-panel/' + training + '/' + course + '/' + module,
            }).execute()
                .done(function () {
                  $wrapper.find(panelSelector).show();
                })
                .always(function () {
                  $this.closest('tr').removeAttr('data-ajax-loading');
                });
          }
        }
        else {
          $panel.show();
        }

        return false;
      });

      var $moduleStep = $('.lp_step_content_module .lp_step_summary_clickable', context);
      $moduleStep.once('click').click(function (e) {
        e.preventDefault();

        var $panels = $('.lp_module_panel', context);
        $panels.hide();

        var $this = $(this);
        var $wrapper = $this.parents('.lp_step_summary_wrapper');
        var training = $this.attr('data-training');
        var module = $this.attr('data-module');
        var panelSelector = '#module_panel_' + training + '_' + module + '[data-ajax-loaded]';
        var $panel = $wrapper.find(panelSelector);
        if ($panel.length === 0) {
          if (!$this.attr('data-ajax-loading')) {
            $this.attr('data-ajax-loading', true);
            Drupal.ajax({
              url: 'ajax/achievements/module-panel/' + training + '/' + module,
            }).execute()
                .done(function () {
                  $wrapper.find(panelSelector).show();
                })
                .always(function () {
                  $this.removeAttr('data-ajax-loading');
                });
          }
        }
        else {
          $panel.show();
        }

        return false;
      });

      var $modulePanelClose = $('.lp_module_panel_close', context);
      $modulePanelClose.once('click').click(function (e) {
        e.preventDefault();

        var $panel = $(this).parents('.lp_module_panel');
        $panel.hide();

        return false;
      });

      var achievementsPage = 0;
      var achievementsAjaxLoading = false;
      var noResult = false;
      var $window = $(window);
      var that = this;

      $window.once('scroll').scroll(function () {
        if (!achievementsAjaxLoading && !noResult) {
          if ($window.scrollTop() >= that.getDocHeight() - (2 * $window.height())) {
            achievementsAjaxLoading = true;
            Drupal.platon.ajaxFullScreenLoader.show();

            Drupal.ajax({
              url: 'ajax/achievements/' + (achievementsPage + 1),
            }).execute()
                .done(function (response) {
                  achievementsPage += 1;
                  Drupal.platon.ajaxFullScreenLoader.hide();

                  if (!response.length) {
                    noResult = true;
                  }
                })
                .always(function (response) {
                  achievementsAjaxLoading = false;
                  Drupal.platon.ajaxFullScreenLoader.hide();

                  if (!response.length) {
                    noResult = true
                  }
                });
          }
        }
      });

      this.donutsCharts(context);
      this.timeLineSlider(context);
    },

    getDocHeight: function () {
      var D = document;
      var max = Math.max(
        D.body.scrollHeight, D.documentElement.scrollHeight,
        D.body.offsetHeight, D.documentElement.offsetHeight,
        D.body.clientHeight, D.documentElement.clientHeight
      );

      return max;
    },

    donutsCharts: function (c) {
      $('.donut', c).each(function () {
        var canvas = $(this)[0];
        var context = canvas.getContext('2d');
        var centerX = canvas.width / 2;
        var centerY = canvas.height / 2;
        var radius = canvas.height / 2;
        var angle = parseInt($(this).attr('data-value'));
        var color = (typeof $(this).attr('data-color') !== 'undefined') ? $(this).attr('data-color') : '#000';
        var radAngle = angle * 2 / 100;
        var trackColor = (typeof $(this).attr('data-track-color') !== 'undefined') ? $(this).attr('data-track-color') : 'rgba(0,0,0,.2)';

        $(this).css('box-shadow', '0 0 0 ' + parseInt($(this).attr('data-width')) / 2 + 'px ' + trackColor + ' inset');

        context.beginPath();
        context.arc(centerX, centerY, radius, -Math.PI / 2, radAngle * Math.PI - Math.PI / 2, false);
        context.lineWidth = parseInt($(this).attr('data-width'));
        context.strokeStyle = color;
        context.stroke();
      });
    },
    timeLineSlider: function (context) {
      $(context).find('.lp_timeline').once('timeLineSlider').each(function () {
        var $timeLine = $(this);
        var $timeLineSlider = $timeLine.clone().addClass('cloned');
        var $step = $timeLine.find('.lp_timeline_step');
        var itemsWidth;
        var resize;

        // Wrap to initial slick slider only for step items.
        $timeLineSlider.find('.lp_timeline_step').wrapAll('<div class="lp_timeline_steps" />');
        $timeLineSlider.find('.lp_timeline_steps').slick({
          dots: true,
          arrows: false,
          infinite: false,
          variableWidth: true,
        });

        $timeLine.after($timeLineSlider);

        $(window).on('resize.timeline', function () {
          // Use setTimeout to perform window resize.
          clearTimeout(resize);
          resize = setTimeout(function () {
            itemsWidth = 126; // start & end items width.

            $step.each(function () {
              itemsWidth += $(this).width();
            });

            // Use zero height in CSS to save origin element width.
            if (itemsWidth > $timeLine.width() && $step.length > 1) {
              $timeLineSlider.show();
              $timeLine.addClass('lp_timeline_step-hide');
            }
            else {
              $timeLineSlider.hide();
              $timeLine.removeClass('lp_timeline_step-hide');
            }

            $timeLineSlider.trigger('setPosition');
          }, 100);
        }).trigger('resize.timeline');
      });
    }
  };
}(jQuery, Drupal, drupalSettings));

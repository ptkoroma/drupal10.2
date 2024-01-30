/**
 * @file
 * Contains the functionality for user statistics block.
 */

(function ($) {

  /**
   * Build charts based on drupalSettings using Charts.js.
   */
  Drupal.behaviors.buildCharts = {
    attach: function (context, settings) {
      if (!$('body').hasClass('charts-rendered')) {
        // Render charts.
        $.each(settings.opignoCharts, function (i, item) {
          var ctx = $(item.id);
          var options = typeof item.options !== 'undefined' ? item.options : [];

          new Chart(ctx, {
            type: item.type,
            plugins: [
              {
                afterInit: function (chart) {
                  // Remove the preloader.
                  $(chart.canvas).closest('.charts-loading').removeClass('charts-loading')
                    .children('.loader').remove();
                },
              },
              {
                afterUpdate: function (chart) {
                  // Rounded corners for the doughnut arc.
                  if (typeof chart.config.options.elements !== 'undefined'
                    && typeof chart.config.options.elements.arc !== 'undefined'
                    && typeof chart.config.options.elements.arc.roundedCornersFor !== 'undefined'
                  ) {
                    var arc = chart.getDatasetMeta(0).data[chart.config.options.elements.arc.roundedCornersFor];
                    arc.round = {
                      x: (chart.chartArea.left + chart.chartArea.right) / 2,
                      y: (chart.chartArea.top + chart.chartArea.bottom) / 2,
                      radius: (arc.outerRadius + arc.innerRadius) / 2,
                      thickness: (arc.outerRadius - arc.innerRadius) / 2 - 1,
                      backgroundColor: arc.options.backgroundColor
                    }
                  }

                  if (typeof chart.config.options.elements !== 'undefined'
                    && typeof chart.config.options.elements.center !== 'undefined'
                  ) {
                    var helpers = Chart.helpers;
                    var centerConfig = chart.config.options.elements.center;
                    var globalConfig = Chart.defaults;
                    var ctx = chart.ctx;

                    var fontStyle = helpers.valueOrDefault(centerConfig.fontStyle, globalConfig.defaultFontStyle);
                    var fontFamily = helpers.valueOrDefault(centerConfig.fontFamily, globalConfig.defaultFontFamily);

                    if (centerConfig.fontSize) {
                      var fontSize = centerConfig.fontSize;
                    }
                    // Find out the best font size, if one is not specified.
                    else {
                      ctx.save();
                      var fontSize = helpers.valueOrDefault(centerConfig.minFontSize, 1);
                      var maxFontSize = helpers.valueOrDefault(centerConfig.maxFontSize, 256);

                      do {
                        ctx.font = helpers.fontString(fontSize, fontStyle, fontFamily);
                        var textWidth = ctx.measureText(centerConfig.text).width;

                        // Check if it fits, is within configured limits and that
                        // we're not simply toggling back and forth.
                        if (textWidth < (arc.innerRadius * 2 - 30) && fontSize < maxFontSize)
                          fontSize += 1;
                        else {
                          // Reverse the last step.
                          fontSize -= 1;
                          break;
                        }
                      } while (true)
                      ctx.restore();
                    }

                    // Save properties
                    chart.center = {
                      font: helpers.fontString(fontSize, fontStyle, fontFamily),
                      fillStyle: helpers.valueOrDefault(centerConfig.fontColor, globalConfig.defaultFontColor)
                    };
                  }
                },
              },
              {
                afterDraw: function (chart) {
                  if (typeof chart.config.options.elements !== 'undefined'
                    && typeof chart.config.options.elements.arc !== 'undefined'
                    && typeof chart.config.options.elements.arc.roundedCornersFor !== 'undefined'
                  ) {
                    var ctx = chart.ctx;
                    var arc = chart.getDatasetMeta(0).data[chart.config.options.elements.arc.roundedCornersFor];
                    var startAngle = Math.PI / 2 - arc.startAngle;
                    var endAngle = Math.PI / 2 - arc.endAngle;

                    ctx.save();
                    ctx.translate(arc.round.x, arc.round.y);
                    ctx.fillStyle = arc.round.backgroundColor;
                    ctx.beginPath();
                    ctx.arc(arc.round.radius * Math.sin(startAngle), arc.round.radius * Math.cos(startAngle), arc.round.thickness, 0, 2 * Math.PI);
                    ctx.arc(arc.round.radius * Math.sin(endAngle), arc.round.radius * Math.cos(endAngle), arc.round.thickness, 0, 2 * Math.PI);
                    ctx.closePath();
                    ctx.fill();
                    ctx.restore();
                  }

                  if (chart.center) {
                    var centerConfig = chart.config.options.elements.center;
                    var ctx = chart.ctx;

                    ctx.save();
                    ctx.font = chart.center.font;
                    ctx.fillStyle = chart.center.fillStyle;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    var centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                    var centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;
                    ctx.fillText(centerConfig.text, centerX, centerY);
                    ctx.restore();
                  }
                },
              },
            ],
            data: {
              labels: item.labels,
              datasets: item.datasets
            },
            options: $.extend(options, {
              responsive: true,
              plugins: {
                legend: {
                  display: false,
                },
                tooltip: {
                  enabled: false,
                },
              },
            }),
          });
        });
        $('body').addClass('charts-rendered');
      }
    }
  }

}(jQuery));

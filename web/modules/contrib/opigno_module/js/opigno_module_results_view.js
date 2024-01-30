(function ($) {
  Drupal.behaviors.opignoModuleResultsView = {
    attach: function () {
      $('#lp-steps-trigger, #block-lp-steps-block').remove();
    }
  };
}(jQuery));

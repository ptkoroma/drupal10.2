/**
 * @file
 * Contains the functionality for admin UI.
 */

(function ($, Drupal, drupalSettings) {

  /**
   * Add label to display 'select all' checkbox on view tables.
   */
  Drupal.behaviors.displaySelectAllCheckbox = {
    attach: function(context) {
      var $checkbox = $('th.select-all input[type="checkbox"]', context);
      var $checkboxId = 'select-all-checkbox';
      var label = document.createElement('label');

      $checkbox.attr('id', $checkboxId);
      label.htmlFor = $checkboxId;
      $(label).addClass('option visually-hidden').insertAfter($checkbox);

      $checkbox.parent().children()
        .wrapAll('<div class="js-form-type-checkbox form-no-label"></div>');
    }
  };

  Drupal.behaviors.displayTranslatableCheckbox = {
    attach: function(context) {
      var $checkboxParent = $('.translatable', context);

      $checkboxParent.find('.form-checkbox').each(function() {
        $(this).after('<label for="' + this.id + '" class="option visually-hidden"></label>');
      });
    }
  };

}(jQuery, Drupal, drupalSettings));

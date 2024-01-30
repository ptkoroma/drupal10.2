(function ($, Drupal) {
  Drupal.behaviors.opignoActivityValidate = {
    attach: function (context, settings) {
      let activityForm = $('form#opigno-activity-opigno-h5p-form');
      if (activityForm.length) {
        let name = activityForm.find('#edit-name-0-value');
        let description = activityForm.find('#edit-name-0-value--description');
        name.on('change', function () {
          let errorDiv = activityForm.find('.h5p-activity-errors');
          let that = $(this);
          if (that.val() === '') {
            if (!errorDiv.length) {
              description.after('<div class="h5p-activity-errors error"><p>' + Drupal.t('The name field is required') + '</p></div>');
            }
            if (!name.hasClass('error')) {
              name.addClass('error');
            }
          }
          else {
            name.removeClass('error');
            if (errorDiv.length) {
              errorDiv.remove();
            }
          }
        });
      }
    },
  };
}(jQuery, Drupal));

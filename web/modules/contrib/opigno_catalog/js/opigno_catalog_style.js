/* eslint-disable */

(function ($, Drupal) {
  Drupal.behaviors.opignoCatalog = {
    attach: function () {
      this.handleViewStyle();

      // Set the selected value from the fake select to the hidden sorting and
      // submit the form.
      // The fake select is needed because the exposed views form is separated to
      // 2 sections according to design: filters on the left column and should
      // refresh the view clicking on the button and sorting at the top of the
      // view results that should refresh the view on change.
      var $fakeSelect = $('#opigno-fake-sort');
      var $sortSelect = $('select[name="sort_bef_combine"]');
      var $selected = $sortSelect.val();

      $fakeSelect.val($selected);
      $fakeSelect.on('change', function () {
        var sort = $(this).val();
        $sortSelect.val(sort);
        $('.opigno-filters-submit').click();
      });
      $('.selectpicker').selectpicker('render');

      // Hide "All" filters item.
      $('input[type="radio"][value="All"]').closest('.form-item').addClass('hidden')
      $('input[name="opigno_group_membership"][value="0"]').closest('.form-item').addClass('hidden')

      // Make it possible to uncheck the membership radiobutton.
      var $myTrainingsRadio = $('.views-exposed-form input[name="opigno_group_membership"][value="1"]');
      $myTrainingsRadio.closest('.form-item').once().on('click', function(e) {
        e.preventDefault();
        var $isChecked = $myTrainingsRadio.prop('checked');
        $myTrainingsRadio.prop('checked', !$isChecked)
        $('input[name="opigno_group_membership"][value="0"]').prop('checked', $isChecked);
      })
    },

    handleViewStyle: function () {
      var that = this;

      $('.view-style a.style-btn').click(function (e) {
        e.preventDefault();
        if ($(this).hasClass('line')) {
          $(this).closest('.view').addClass('style-line');
          that.setStyle('line');
        } else {
          $(this).closest('.view').removeClass('style-line');
          that.setStyle('block');
        }
      });

      // Show on mobile only block view.
      if ($(window).width() < 768) {
        $(this).closest('.view').removeClass('style-line');
        that.setStyle('block');
      }
    },

    setStyle: function (style) {
      var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';
      $.ajax({
        type: 'get',
        url: baseUrl + 'opigno-catalog/set-style/'+ style,
        success: function (data) { },
        error: function (data) {
          console.error('The ajax request has encountered a problem');
        }
      });
    }
  };
}(jQuery, Drupal));

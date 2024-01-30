(function ($, Drupal) {
  Drupal.behaviors.opignoMobileMode = {
    attach: function (context) {
      /**
       *  Add table heads to rows.
       */
      var headertext = [],
          selector = "#folder-content-container table";
          headers = document.querySelectorAll(selector + " th"),
          tablerows = document.querySelectorAll(selector + " th"),
          tablebody = document.querySelector(selector + " tbody");

      for(var i = 0; i < headers.length; i++) {
        var current = headers[i];
        headertext.push(current.textContent.replace(/\r?\n|\r/,""));
      }
      for (var i = 0, row; row = tablebody.rows[i]; i++) {
        for (var j = 0, col; col = row.cells[j]; j++) {
          col.setAttribute("data-th", headertext[j]);
        }
      }

    },
  };
}(jQuery, Drupal, drupalSettings));
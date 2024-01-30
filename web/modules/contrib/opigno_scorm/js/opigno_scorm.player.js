/**
 * @file
 * JS UI logic for SCORM player.
 *
 * @see js/lib/player.js
 * @see js/lib/api.js
 */

;(function($, Drupal, window, undefined) {

  Drupal.behaviors.opignoScormPlayer = {

    attach: function(context, settings) {
      // Initiate the API.
      if (settings.scormVersion === '1.2') {
        var scormAPIobject = window.API;
        if (scormAPIobject === undefined) {
          scormAPIobject = new OpignoScorm12API(settings.scorm_data || {});
        }
      }
      else {
        var scormAPIobject = window.API_1484_11;
        if (window.API_1484_11 === undefined) {
          window.API_1484_11 = new OpignoScorm2004API(settings.scorm_data || {});
          scormAPIobject = window.API_1484_11;
        }

        // Register scos suspend data.
        if (settings.opignoScormUIPlayer && settings.opignoScormUIPlayer.cmiSuspendItems) {
          window.API_1484_11.registerSuspendItems(settings.opignoScormUIPlayer.cmiSuspendItems);
        }
      }

      // Register CMI paths.
      if (settings.opignoScormUIPlayer && settings.opignoScormUIPlayer.cmiPaths) {
        scormAPIobject.registerCMIPaths(settings.opignoScormUIPlayer.cmiPaths);
      }

      // Register default CMI data.
      if (settings.opignoScormUIPlayer && settings.opignoScormUIPlayer.cmiData) {
        for (var item in settings.opignoScormUIPlayer.cmiData) {
          scormAPIobject.registerCMIData(item, settings.opignoScormUIPlayer.cmiData[item]);
        }
      }

      // Get all SCORM players in our context.
      var $players = $('.scorm-ui-player', context);

      // If any players were found...
      if ($players.length) {
        // Register each player.
        // NOTE: SCORM only allows on SCORM package on the page at any given time.
        // Skip after the first one.
        var first = true;
        $players.each(function() {
          if (!first) {
            return false;
          }

          var element = this,
              $element = $(element),
              // Create a new OpignoScormUIPlayer().
              player = new OpignoScormUIPlayer(element),
              alertDataStored = false;

          player.init();

          var is12 = settings.scormVersion === '1.2';
          var commitEventName = 'commit' + (is12 ? '12' : '');
          var postSetValueEventName = 'post-setvalue' + (is12 ? '12' : '');


          // Listen on commit event, and send the data to the server.
          scormAPIobject.bind(commitEventName, function(value, data, scoId) {
            var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';
            if (navigator.sendBeacon) {
              let url = baseUrl + 'opigno-scorm/scorm/' + $element.data('scorm-id') + '/' + scoId + '/commit';
              let json = JSON.stringify(data);
              navigator.sendBeacon(url, json);
            }
            else {
              $.ajax({
                url: baseUrl + 'opigno-scorm/scorm/' + $element.data('scorm-id') + '/' + scoId + '/commit',
                data: { data: JSON.stringify(data) },
                async:   false,
                dataType: 'json',
                type: 'post',
                success: function(json) {
                  if (alertDataStored) {
                    console.log(Drupal.t('We successfully stored your results. You can now proceed further.'));
                  }
                }
              });
            }
          });
          scormAPIobject.bind(postSetValueEventName, function (cmiElement, value) {
            if (cmiElement === 'cmi.suspend_data') {
              commitCallback();
            }
          });


          $("#edit-submit").bind("click", function () {
              var $el = $(document),
              $iframe = $el.find('.scorm-ui-player-iframe-wrapper iframe'),
              iframe = $iframe[0];
              var scoId = iframe.src.split('opigno-scorm/player/sco/').pop();
              var baseUrl = drupalSettings.path.baseUrl ? drupalSettings.path.baseUrl : '/';
              $.ajax({
                  url: baseUrl + 'opigno-scorm/scorm/' + $element.data('scorm-id') + '/' + scoId + '/commit',
                  data: { data: JSON.stringify(scormAPIobject.data) },
                  async: false,
                  dataType: 'json',
                  type: 'post',
                  success: function (json) {
                      if (alertDataStored) {
                          console.log(Drupal.t('We successfully stored your results. You can now proceed further.'));
                      }
                  }
             });
          });

          // Listen to the unload event. Some users click "Next" or go to a different page, expecting
          // their data to be saved. We try to commit the data for them, hoping ot will get stored.
          var commitCallback = function() {
            if (settings.scormVersion === '1.2') {
              if (!scormAPIobject.isFinished) {
                scormAPIobject.LMSCommit('');
                alertDataStored = true;
              }
            }
            else {
              if (!scormAPIobject.isTerminated) {
                scormAPIobject.Commit('');
                alertDataStored = true;
              }
            }
          };
          $(window).bind('beforeunload', commitCallback);
          // Trigger commit every minute.
          setInterval(commitCallback, 30000)

          // Add a class to the player, so the CSS can style it differently if needed.
          $element.addClass('js-processed');

          first = false;
        });
      }
    }

  };

})(jQuery, Drupal, window);

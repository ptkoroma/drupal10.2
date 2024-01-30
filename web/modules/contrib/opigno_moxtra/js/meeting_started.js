(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.opignoMoxtraMeetingStarted = {
    attach: function (context, settings) {
      mepsdk.setup({
        baseDomain: drupalSettings.opignoMoxtra.baseDomain,
        deployDirectory: 'web',
        accessToken: drupalSettings.opignoMoxtra.accessToken
      });

      mepsdk.joinMeetWithMeetID(drupalSettings.opignoMoxtra.sessionKey, '#live-meeting-container', { autoJoinAudio: true });
    }
  };
}(jQuery, Drupal, drupalSettings));

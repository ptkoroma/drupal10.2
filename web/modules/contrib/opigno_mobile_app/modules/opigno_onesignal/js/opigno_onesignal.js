(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.oneSignal = {
    attach: function (context, settings) {
      var that = this;
      // OneSignal.push(function () {
      //   OneSignal.once('checkSubscription').on("subscriptionChange", function (isSubscribed) {
      //     if (isSubscribed === true) {
      //       OneSignal.getUserId().then(function (userId) {
      //         var player_id = userId;
      //         var operation = 'store';
      //         that.updateOneSignalSubscriptionStatus(player_id, operation);
      //       });
      //     }
      //     else if (isSubscribed === false) {
      //       console.log('debug 4');
      //       var player_id = null;
      //       var operation = 'delete';
      //       // Trigger callback.
      //       that.updateOneSignalSubscriptionStatus(player_id, operation);
      //     }
      //   });
      // });
      // Set external id to identify current user by One Signal.
      OneSignal.push(function () {
        OneSignal.getExternalUserId().then(function (external_id) {
          if (external_id !== drupalSettings.opigno_onesignal.uuid) {
            OneSignal.setExternalUserId(drupalSettings.opigno_onesignal.uuid).then(function (data) {
              console.log('User external id was updated');
            });
          }
        });
      });
    },

    // updateOneSignalSubscriptionStatus: function (uid, operation) {
    //   $.ajax({
    //     url: "/opigno_onesignal/store-player-id",
    //     method: "POST",
    //     data: {
    //       op: operation,
    //       player_id: uid
    //     },
    //     success: function (data) {
    //       console.log("User subscription status was updated");
    //     }
    //   });
    // }
  }
}(jQuery, Drupal, drupalSettings));

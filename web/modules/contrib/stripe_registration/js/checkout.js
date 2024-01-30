/**
 * @file
 */
Drupal.behaviors.stripe_registration = {
    attach: function (context, settings) {

        function initializeStripe(Stripe) {
            document.querySelectorAll('.stripe-subscribe-button').forEach(function(elem) {
                elem.addEventListener('click', function(event) {
                    stripeSubscribeClickEventListen(event);
                });
            });
        }

        function stripeSubscribeClickEventListen(event) {
            var targetElement = event.target;
            var throbber = '<span class="ajax-progress__throbber">&nbsp;</span>';
            jQuery(targetElement).prop('disabled', true).html('Please wait...' + throbber);
            jQuery.ajax({
                // @todo Get this url from the backend.
                url: "/stripe_registration/subscribe/create_subscribe_session",
                method: "POST",
                dataType: 'json',
                data: {
                    price_id: targetElement.dataset.price_id,
                    return_url: getUrlParameter('return_url')
                },
                success: function(data, textStatus, jqXHR) {
                    redirectToStripe(Stripe, data.session_id, data.public_key);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    window.alert("Something went wrong. Please contact support@guitargate.com and report the following error:\n\n " + errorThrown + ':\n' + jqXHR.responseText);
                }
            });
        }

        function redirectToStripe(Stripe, sessionId, publicKey) {
            var stripe = Stripe(publicKey);
            stripe.redirectToCheckout({
                sessionId: sessionId
            }).then(function (result) {
                // If `redirectToCheckout` fails due to a browser or network
                // error, display the localized error message to your customer
                // using `result.error.message`.
            });
        }

        function getUrlParameter(sParam) {
          var sPageURL = window.location.search.substring(1),
            sURLVariables = sPageURL.split('&'),
            sParameterName,
            i;

          for (i = 0; i < sURLVariables.length; i++) {
            sParameterName = sURLVariables[i].split('=');

            if (sParameterName[0] === sParam) {
              return sParameterName[1] === undefined ? true : decodeURIComponent(sParameterName[1]);
            }
          }
        }

        (function ($) {
            initializeStripe(Stripe);
        })(jQuery, Stripe);
    }


};

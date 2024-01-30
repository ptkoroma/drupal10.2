/**
 * @file
 * Defines behaviors for the Stripe Payment Element payment method form.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the commerceStripePaymentElement behavior.
   */
  Drupal.behaviors.commerceStripePaymentElement = {
    attach: function (context) {
      if (!drupalSettings.commerceStripePaymentElement || !drupalSettings.commerceStripePaymentElement.publishableKey) {
        return;
      }

      const settings = drupalSettings.commerceStripePaymentElement;
      $(once('stripe-processed', '#' + settings.elementId, context)).each(function () {
        var $form = $(this).closest('form');

        // Create a Stripe client.
        var stripe = Stripe(settings.publishableKey);

        // Show Stripe Payment Element form.
        if (settings.showPaymentForm) {
          // Create an instance of Stripe Elements.
          var elements = stripe.elements(settings.createElementsOptions);
          var paymentElement = elements.create('payment', settings.paymentElementOptions);
          paymentElement.mount('#' + settings.elementId);

          $form.on('submit.stripe_payment_element', function (e) {
            e.preventDefault();
            $form.find(':input.button--primary').prop('disabled', true);
            // Confirm the card payment.
            stripe.confirmPayment({
              elements,
              confirmParams: {
                return_url: settings.returnUrl
              }
            }).then(function (result) {
              if (result.error) {
                // Inform the user if there was an error.
                // Display the message error in the payment form.
                Drupal.commerceStripe.displayError(result.error.message);
                // Allow the customer to re-submit the form.
                $form.find(':input.button--primary').prop('disabled', false);
              }
            });
          });
        }
        // Confirm a payment by payment method.
        else {
          var allowSubmit = false;
          $form.on('submit.stripe_payment_element', function (e) {
            $form.find(":input.button--primary").prop("disabled", true);
            if (!allowSubmit) {
              $form.find(":input.button--primary").prop("disabled", true);
              stripe.confirmCardPayment(
                settings.clientSecret,
                {
                  payment_method: settings.paymentMethod
                }
              ).then(function (result) {
                if (result.error) {
                  Drupal.commerceStripe.displayError(result.error.message);
                  $form.find(':input.button--primary').prop('disabled', false);
                }
                else {
                  allowSubmit = true;
                  $form.submit();
                }
              });
              return false;
            }
            return true;
          });
        }
      });
    },
    detach: function detach(context, settings, trigger) {
      if (trigger !== "unload") {
        return;
      }
      var $form = $("[id^=" + drupalSettings.commerceStripePaymentElement.elementId + "]", context).closest("form");
      if ($form.length === 0) {
        return;
      }
      $form.off("submit.stripe_payment_element");
    }
  };

})(jQuery, Drupal, drupalSettings);

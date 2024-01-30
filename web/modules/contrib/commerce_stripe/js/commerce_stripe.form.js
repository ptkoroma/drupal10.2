/**
 * @file
 * Javascript to generate Stripe token in PCI-compliant way.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Attaches the commerceStripeForm behavior.
   *
   * @type {Drupal~behavior}
   *
   * @prop object cardNumber
   *   Stripe card number element.
   * @prop object cardExpiry
   *   Stripe card expiry element.
   * @prop object cardCvc
   *   Stripe card cvc element.
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the commerceStripeForm behavior.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches the commerceStripeForm behavior.
   *
   * @see Drupal.commerceStripe
   */
  Drupal.behaviors.commerceStripeForm = {
    cardNumber: null,
    cardExpiry: null,
    cardCvc: null,
    stripe: null,

    attach: function (context) {
      var self = this;
      if (!drupalSettings.commerceStripe || !drupalSettings.commerceStripe.publishableKey) {
        return;
      }
      $(once('stripe-processed', '.stripe-form', context)).each(function () {
        var $form = $(this).closest('form');

        // Create a Stripe client.
        /* global Stripe */
        try {
          if (!self.stripe) {
            self.stripe = Stripe(drupalSettings.commerceStripe.publishableKey);
          }
          var stripe = self.stripe;
        }
        catch (e) {
          $form.find('#payment-errors').html(Drupal.theme('commerceStripeError', e.message));
          $form.find(':input.button--primary').prop('disabled', true);
          $(this).find('.form-item').hide();
          return;
        }

        // Create an instance of Stripe Elements.
        var elements = stripe.elements();
        var classes = {
          base: 'form-text',
          invalid: 'error'
        };
        // Create instances of the card elements.
        self.cardNumber = elements.create('cardNumber', {
          showIcon: drupalSettings.commerceStripe.enable_credit_card_logos,
          classes: classes,
          placeholder: ''
        });
        self.cardExpiry = elements.create('cardExpiry', {
          classes: classes
        });
        self.cardCvc = elements.create('cardCvc', {
          classes: classes
        });
        // Add an instance of the card UI components into the "scard-element" element <div>
        self.cardNumber.mount('#card-number-element');
        self.cardExpiry.mount('#expiration-element');
        self.cardCvc.mount('#security-code-element');

        // Input validation.
        self.cardNumber.on('change', function (event) {
          stripeErrorHandler(event);
        });
        self.cardExpiry.on('change', function (event) {
          stripeErrorHandler(event);
        });
        self.cardCvc.on('change', function (event) {
          stripeErrorHandler(event);
        });

        // Helper to handle the Stripe responses with errors.
        var stripeErrorHandler = function (result) {
          if (result.error) {
            // Inform the user if there was an error.
            // Display the message error in the payment form.
            Drupal.commerceStripe.displayError(result.error.message);

            // Allow the customer to re-submit the form.
            $form.find(':input.button--primary').prop('disabled', false);
          }
          else {
            // Clean up error messages.
            $form.find('#payment-errors').html('');
          }
        };

        // Helper to get billing details.
        var stripeBillingDetails = function () {
          var name = [], details = {},
            copyFields = $('input[name=payment_information\\[add_payment_method\\]\\[billing_information\\]\\[copy_fields\\]\\[enable\\]]').is(':checked');
          // Get billing details from payment or shipping as configured.
          $('.checkout-pane-' + (copyFields ? 'shipping' : 'payment') + '-information [data-stripe]').each(function() {
            var element = $(this), field = element.attr('data-stripe').split('.');
            var value = element.val();
            if (value) {
              switch (field[0]) {
                case 'email':
                  details.email = value;
                  break;
                case 'name':
                  name[parseInt(field[1])] = value;
                  break;
                case 'address':
                  if (!details.address) {
                    details.address = {}
                  }
                  details.address[field[1]] = value;
                  break;
              }
            }
          });
          // If no email field was found in payment/shipping, use the first
          // matching field in other panes.
          if (!details.email) {
            var email = $('.checkout-pane [data-stripe="email"]').first().val();
            if (email) {
              details.email = email;
            }
          }
          if (name.length) {
            details.name = name.join(' ').trim();
          }

          return details;
        }

        // Form submit.
        $form.on('submit.commerce_stripe', function (e) {
          if ($('#stripe-payment-method-id', $form).val().length > 0) {
            return true;
          }
          if (drupalSettings.commerceStripe.clientSecret === null) {
            // Try to create the Stripe payment method and submit the form.
            stripe.createPaymentMethod({
              type: 'card',
              card: self.cardNumber,
              billing_details: stripeBillingDetails()
            }).then(function (result) {
              if (result.error) {
                // Inform the user if there was an error.
                stripeErrorHandler(result);
              } else {
                $('#stripe-payment-method-id', $form).val(result.paymentMethod.id);
                $form.find(':input.button--primary').click();
              }
            });
          } else {
            stripe.handleCardSetup(drupalSettings.commerceStripe.clientSecret, self.cardNumber, {
              payment_method_data: {
                billing_details: stripeBillingDetails()
              }
            }).then(function (result) {
              if (result.error) {
                // Inform the user if there was an error.
                stripeErrorHandler(result);
              }
              else {
                // Insert the payment method ID into the form so it gets submitted to
                // the server.
                // Set the Stripe token value.
                $('#stripe-payment-method-id', $form).val(result.setupIntent.payment_method);
                // Submit the form.
                $form.find(':input.button--primary').click();
              }
            });
          }

          // Prevent the form from submitting with the default action.
          if ($('#card-number-element', $form).length) {
            return false;
          }
        });
      });
    },

    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      var self = this;
      ['cardNumber', 'cardExpiry', 'cardCvc'].forEach(function (i) {
        if (self[i] && self[i].length > 0) {
          self[i].unmount();
          self[i] = null;
        }
      });
      var $form = $('.stripe-form', context).closest('form');
      if ($form.length === 0) {
        return;
      }
      $form.off('submit.commerce_stripe');
    }
  };

})(jQuery, Drupal, drupalSettings);

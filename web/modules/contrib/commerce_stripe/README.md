Commerce Stripe
===============

CONTENTS OF THIS FILE
---------------------
* Introduction
* Requirements
* Configuration

INTRODUCTION
------------
This module integrates Drupal Commerce with various Stripe payment solutions,
including the Payment Element [1] and the legacy Card Element [2].

1. https://stripe.com/docs/payments/payment-element
2. https://stripe.com/docs/payments/accept-card-payments?platform=web&ui=elements

Stripe supports many payment method types, including credit card, mobile
wallets (e.g., Apple Pay), bank transfers, and more. Both element integrations
support advanced fraud detection, Strong Customer Authentication (e.g., 3D
Secure), and secure payment method tokenization.

## Features

* Configure Payment Element for use on the review page
* Configure the legacy Card Element for use in the payment checkout pane
* Uses the Stripe.js library that ensures card data never touches your server
* Payments in Drupal Commerce synchronized with Stripe
* Supports voids, captures, and refunds through the order management interface


REQUIREMENTS
------------
This module should be added to your codebase via Composer to ensure the Stripe
PHP library dependency is properly managed:

`composer require "drupal/commerce_stripe:^1.0"`

You must also have a Stripe merchant account or developer access to the account
you intend to configure for your integration. You can signup for one here:

* https://dashboard.stripe.com/register


CONFIGURATION
-------------
Once you've installed the module, you must navigate to the Drupal Commerce
payment gateway configuration screen to define a payment gateway configuration.
This will require providing API keys and configuring the mode (Live vs. Test)
along with a variety of appearance related options depending on the plugin.

Note: Drupal Commerce recommends storing live API credentials outside of the
configuration object and importing them via a configuration override in your
settings.php file. To accomplish this you can input only your test credentials
for validation in the configuration form or input `PLACEHOLDER` and uncheck the
box that instructs the configuration form to validate your API keys.

Payment Element is the current, recommended element Stripe prefers merchants to
use. This element will embed an iframe on the review page that supports credit
card, payment wallet, and a variety of other payment methods. The Card Element
integration is a legacy integration that will incorporate credit card fields on
the payment information checkout pane.

Both methods support 3D Secure. You _must_ ensure the Stripe review checkout
pane is enabled on the Review page for any checkout flow that supports either
Stripe element. Removing it will break the Payment Element integration and
prevent the Card Element integration from authorizing payments via 3D Secure.

## Stripe account configuration

In your Stripe account settings, you can adjust the payment methods available
through these elements. To enable Apple Pay support, you will need to
authenticate your domain(s) and ensure a certificate is made accessible on
your web server. (Some server configurations or PaaS build configurations will
need to be adjusted to ensure the web server will allow access to file in the
`/.well-known` directory.)

The Payment Element integration supports webhooks, which keep payments in
Drupal Commerce synchronized with the charges in Stripe when an administrator
updates the payment from the Stripe dashboard. You must configure the webhook
in the Developers section of your Stripe dashboard, adding an endpoint for the
following path on your domain: `/payment/notify/stripe_payment_element`

Enable webhooks for the following events:

* payment_intent.amount_capturable_updated
* payment_intent.canceled
* refund.created
* charge.refunded
* payment_intent.processing
* payment_intent.succeeded
* payment_intent.payment_failed

You may enable other events as needed based on your own customizations. You can
use the webhooks inteerface in the Stripe dashboard to view all webhooks sent
to the endpoint. Settings in the Drupal module can be toggled to also log some
webhook related notifications to the site.

## Customizing the JavaScript settings

Stripe elements are initialized via JavaScript using settings arrays that are
outputted by the module. The module does not accommodate _every_ setting you
might want to adjust for your site. To change or add settings before an element
is initialized, you can use `hook_js_settings_alter()`. This module uses a
different key for each of the elements it supports to make finding the correct
array a little easier.

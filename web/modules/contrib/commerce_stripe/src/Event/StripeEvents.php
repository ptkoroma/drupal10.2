<?php

namespace Drupal\commerce_stripe\Event;

/**
 * Defines events for the Commerce Stripe module.
 */
class StripeEvents {

  /**
   * Name of the event fired before the PaymentIntent is created.
   *
   * This allows subscribers to add or modify intent attributes and metadata.
   *
   * @Event
   *
   * @see https://stripe.com/docs/api/payment_intents/create
   * @see \Drupal\commerce_stripe\Event\PaymentIntentEvent
   */
  const PAYMENT_INTENT_CREATE = 'commerce_stripe.payment_intent_create';

  /**
   * Name of the event fired to add additional transaction data.
   *
   * @deprecated Will be removed in 2.x. Use StripeEvents::PAYMENT_INTENT_CREATE
   * to modify the payment intent attributes.
   *
   * @Event
   *
   * @see https://stripe.com/blog/adding-context-with-metadata
   * @see https://stripe.com/docs/api#metadata
   * @see \Drupal\commerce_stripe\Event\TransactionDataEvent
   */
  const TRANSACTION_DATA = 'commerce_stripe.transaction_data';

  /**
   * Name of the event fired when a payment method is about to be created.
   *
   * This event is triggered by commerce_stripe.form.js when the checkout
   * payment information form submit button is clicked. It is dispatched
   * before any Drupal form submit handlers run, before the remote payment
   * method is created at Stripe, and before the local Commerce payment method
   * entity is saved. Subscribers may use this event to customise the remote
   * payment method setup at Stripe.
   *
   * @Event
   *
   * @see \Drupal\commerce_stripe\Event\PaymentMethodCreateEvent
   */
  const PAYMENT_METHOD_CREATE = 'commerce_stripe.payment_method_create';

}

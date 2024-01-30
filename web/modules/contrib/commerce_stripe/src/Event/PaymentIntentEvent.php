<?php

namespace Drupal\commerce_stripe\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Defines the payment intent event.
 *
 * This enables other modules to alter the intent attributes sent to Stripe.
 *
 * @see \Drupal\commerce_stripe\Event\StripeEvents
 */
class PaymentIntentEvent extends EventBase {

  /**
   * The order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected OrderInterface $order;

  /**
   * The intent attributes array.
   *
   * @var array
   */
  protected array $intentAttributes;

  /**
   * The payment object.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentInterface|null
   */
  protected $payment;

  /**
   * Constructs a new PaymentIntentCreateEvent object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $intent_attributes
   *   The intent attributes.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|null $payment
   *   The payment, NULL if not set.
   */
  public function __construct(OrderInterface $order, array $intent_attributes = [], PaymentInterface $payment = NULL) {
    $this->order = $order;
    $this->intentAttributes = $intent_attributes;
    $this->payment = $payment;
  }

  /**
   * Gets the intent attributes array.
   *
   * @return array
   *   The intent attributes.
   */
  public function getIntentAttributes(): array {
    return $this->intentAttributes;
  }

  /**
   * Sets the intent attributes array.
   *
   * @param array $intent_attributes
   *   The intent attributes array.
   *
   * @return $this
   */
  public function setIntentAttributes(array $intent_attributes) {
    $this->intentAttributes = $intent_attributes;
    return $this;
  }

  /**
   * Adds or modifies specific intent attributes.
   *
   * @param array $intent_attributes
   *   The intent attributes array.
   *
   * @return $this
   */
  public function addIntentAttributes(array $intent_attributes) {
    $this->intentAttributes = NestedArray::mergeDeep($this->intentAttributes, $intent_attributes);
    return $this;
  }

  /**
   * Gets the payment object if available.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface|null
   *   The payment object.
   */
  public function getPayment(): ?PaymentInterface {
    return $this->payment;
  }

  /**
   * Get the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

}

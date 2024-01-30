<?php

namespace Drupal\commerce_stripe\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Defines the payment method create event.
 *
 * This enables other modules to alter the payment method creation.
 */
class PaymentMethodCreateEvent extends EventBase {

  /**
   * The payment method.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentMethodInterface
   */
  protected $paymentMethod;

  /**
   * The payment details.
   *
   * @var array
   */
  protected $paymentDetails = [];

  /**
   * Constructs a new TransactionDataEvent object.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The data supplied to the payment method.
   */
  public function __construct(PaymentMethodInterface $payment_method, array $payment_details) {
    $this->paymentMethod = $payment_method;
    $this->paymentDetails = $payment_details;
  }

  /**
   * Gets the payment method.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentMethodInterface
   *   The payment method.
   */
  public function getPaymentMethod() {
    return $this->paymentMethod;
  }

  /**
   * Gets the payment details data.
   *
   * @return array
   *   The payment details data.
   */
  public function getPaymentDetails() {
    return $this->paymentDetails;
  }

}

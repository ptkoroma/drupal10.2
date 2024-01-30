<?php

namespace Drupal\commerce_stripe\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Stripe\Exception\ApiErrorException as StripeError;
use Stripe\PaymentIntent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to order events to syncronize orders with their payment intents.
 *
 * Payment intents contain the amount which should be charged during a
 * transaction. When a payment intent is confirmed server or client side, that
 * amount is what is charged. To ensure a proper charge amount, we must update
 * the payment intent amount whenever an order is updated.
 */
class OrderPaymentIntentSubscriber implements EventSubscriberInterface, DestructableInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The minor units converter.
   *
   * @var \Drupal\commerce_price\MinorUnitsConverterInterface
   */
  protected $minorUnitsConverter;

  /**
   * The intent IDs that need updating.
   *
   * @var int[]
   */
  protected $updateList = [];

  /**
   * Constructs a new OrderEventsSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $minor_units_converter
   *   The minor units converter.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MinorUnitsConverterInterface $minor_units_converter) {
    $this->entityTypeManager = $entity_type_manager;
    $this->minorUnitsConverter = $minor_units_converter;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OrderEvents::ORDER_UPDATE => 'onOrderUpdate',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    foreach ($this->updateList as $intent_id => $amount) {
      try {
        $intent = PaymentIntent::retrieve($intent_id);
        // You may only update the amount of a PaymentIntent with one of the
        // following statuses: requires_payment_method, requires_confirmation.
        if (in_array($intent->status, [
          PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
          PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
        ], TRUE)) {
          PaymentIntent::update($intent_id, ['amount' => $amount]);
        }
      }
      catch (StripeError $e) {
        // Allow sync errors to silently fail.
      }
    }
  }

  /**
   * Ensures the Stripe payment intent is up to date.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   */
  public function onOrderUpdate(OrderEvent $event) {
    $order = $event->getOrder();
    $gateway = $order->get('payment_gateway');
    if ($gateway->isEmpty() || !$gateway->entity instanceof PaymentGatewayInterface) {
      return;
    }
    $plugin = $gateway->entity->getPlugin();
    if (
      !($plugin instanceof StripeInterface) &&
      !($plugin instanceof StripePaymentElementInterface)
    ) {
      return;
    }
    $intent_id = $order->getData('stripe_intent');
    if ($intent_id === NULL) {
      return;
    }
    $total_price = $order->getTotalPrice();
    if ($total_price !== NULL) {
      $amount = $this->minorUnitsConverter->toMinorUnits($order->getTotalPrice());
      $this->updateList[$intent_id] = $amount;
    }
  }

}

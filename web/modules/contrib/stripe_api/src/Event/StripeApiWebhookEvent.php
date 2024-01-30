<?php

namespace Drupal\stripe_api\Event;

use Stripe\Event;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

/**
 * Class StripeApiWebhookEvent.
 *
 * Provides the Stripe API Webhook Event.
 */
class StripeApiWebhookEvent extends SymfonyEvent {

  /**
   * Webhook event type.
   *
   * @var string
   */
  public $type;

  /**
   * Webhook event data.
   *
   * @var array
   */
  public $data;

  /**
   * Stripe event object.
   *
   * @var \Stripe\Event
   */
  public $event;

  /**
   * Sets the default values for the event.
   *
   * @param string $type
   *   Webhook event type.
   * @param object $data
   *   Webhook event data.
   * @param \Stripe\Event $event
   *   Stripe event object.
   */
  public function __construct(string $type, $data, Event $event = NULL) {
    $this->type = $type;
    $this->data = $data;
    $this->event = $event;
  }

}

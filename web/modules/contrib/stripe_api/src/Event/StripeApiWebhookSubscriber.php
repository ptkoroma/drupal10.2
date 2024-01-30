<?php

namespace Drupal\stripe_api\Event;

use Drupal\Component\Serialization\Json;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class StripeApiWebhookSubscriber.
 *
 * Provides the webhook subscriber functionality.
 */
class StripeApiWebhookSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['stripe_api.webhook'][] = ['onIncomingWebhook'];
    return $events;
  }

  /**
   * Process an incoming webhook.
   *
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   *   Logs an incoming webhook of the setting is on.
   */
  public function onIncomingWebhook(StripeApiWebhookEvent $event) {
    $config = \Drupal::config('stripe_api.settings');
    if ($config->get('log_webhooks')) {
      \Drupal::logger('stripe_api')
        ->info('Processed webhook: @name<br /><br />Data: @data', [
          '@name' => $event->type,
          '@data' => Json::encode($event->data),
        ]);
    }
  }

}

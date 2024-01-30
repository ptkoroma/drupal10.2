<?php

namespace Drupal\stripe_registration\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\stripe_api\Event\StripeApiWebhookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\stripe_registration\StripeRegistrationService;

/**
 * Class WebHookSubscriber.
 *
 * @package Drupal\stripe_registration
 */
class WebHookSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\stripe_registration\StripeRegistrationService*/
  protected $stripeRegApi;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface*/
  protected $logger;

  /**
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * WebHookSubscriber constructor.
   *
   * @param \Drupal\stripe_registration\StripeRegistrationService $stripe_registration_stripe_api
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   */
  public function __construct(StripeRegistrationService $stripe_registration_stripe_api, LoggerChannelInterface $logger, MessengerInterface $messenger) {
    $this->stripeRegApi = $stripe_registration_stripe_api;
    $this->logger = $logger;
    $this->messenger = $messenger;
  }

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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   * @throws \Throwable
   */
  public function onIncomingWebhook(StripeApiWebhookEvent $event) {
    $type = $event->type;
    $data = $event->data;
    $stripe_event = $event->event;
    $this->logEvent($event, $stripe_event);

    // React to subscription life cycle events.
    // @see https://stripe.com/docs/subscriptions/lifecycle
    switch ($type) {
      // Occurs whenever a customer with no subscription is signed up for a plan.
      case 'customer.subscription.created':
        $remote_subscription = $data->object;
        $this->createOrUpdateLocalSubscription($remote_subscription);

        break;

      case 'customer.subscription.updated':
        // When a Stripe customer is created, BOTH the customer.subscription.created
        // AND customer.subscription.updated events are fired. To prevent this triggering
        // the creation of the same local subscription at the same time, we introduce a
        // small delay here.
        sleep(5);
        $remote_subscription = $data->object;
        $this->createOrUpdateLocalSubscription($remote_subscription);

        break;

      // Occurs whenever a customer ends their subscription.
      case 'customer.subscription.deleted':
        $remote_subscription = $data->object;
        $this->deleteLocalSubscription($remote_subscription);

        break;

      // Occurs three days before the trial period of a subscription is scheduled to end.
      case 'customer.subscription.trial_will_end':
        break;
    }

  }

  /**
   * @param $remote_subscription
   *
   * @throws \Throwable
   */
  protected function createLocalSubscription($remote_subscription): void {
    try {
      $local_subscription = $this->stripeRegApi->createLocalSubscription($remote_subscription);
      $this->messenger->addMessage(t('You have successfully subscribed to the @plan_name plan.',
        ['@plan_name' => $remote_subscription->plan->name]), 'status');
      $this->logger->debug('Created local subscription #@subscription_id with remote ID @remote_id', ['@subscription_id' => $local_subscription->id(), '@remote_id' => $remote_subscription->id]);
    } catch (\Throwable $e) {
      $this->logger->error('Failed to create local subscription for remote subscription @remote_id: @exception', ['@exception' => $e->getMessage() . $e->getTraceAsString(), '@remote_id' => $remote_subscription->id]);
      throw $e;
    }
  }

  /**
   * @param $remote_subscription
   *
   * @throws \Throwable
   */
  protected function deleteLocalSubscription($remote_subscription): void {
    try {
      $this->stripeRegApi->syncRemoteSubscriptionToLocal($remote_subscription->id);
      $local_subscription = $this->stripeRegApi->loadLocalSubscription(['subscription_id' => $remote_subscription->id]);
      $local_subscription->delete();
    } catch (\Throwable $e) {
      $this->logger->error('Failed to delete local subscription @remote_id: @exception', ['@exception' => $e->getMessage() . $e->getTraceAsString(), '@remote_id' => $remote_subscription->id]);
      throw $e;
    }
  }

  /**
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   * @param \Stripe\Event $stripe_event
   */
  protected function logEvent(StripeApiWebhookEvent $event, \Stripe\Event $stripe_event): void {
    if (\Drupal::config('stripe_api.settings')->get('log_webhooks')) {
      $this->logger->info("Event Subscriber reacting to @type event:\n @event",
        ['@type' => $event->type, '@event' => json_encode($stripe_event, JSON_PRETTY_PRINT)]);
    }
  }

  /**
   * @param $remote_subscription
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   * @throws \Throwable
   */
  protected function createOrUpdateLocalSubscription($remote_subscription): void {
    $local_subscription = $this->stripeRegApi->loadLocalSubscription(['subscription_id' => $remote_subscription->id]);
    if (!$local_subscription) {
      $this->createLocalSubscription($remote_subscription);
    }
    else {
      $this->stripeRegApi->syncRemoteSubscriptionToLocal($remote_subscription->id);
    }
  }

}

<?php

namespace Drupal\stripe_registration;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\stripe_api\StripeApiService;
use Drupal\user\Entity\User;
use Stripe\Customer;
use Stripe\Plan;
use Stripe\Product;
use Stripe\Subscription;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class StripeRegistrationService.
 *
 * @package Drupal\stripe_registration
 */
class StripeRegistrationService {

  use MessengerTrait;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * @var \Drupal\stripe_api\StripeApiService
   */
  protected $stripeApi;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\stripe_api\StripeApiService $stripe_api
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, LoggerChannelInterface $logger, StripeApiService $stripe_api) {
    $this->config = $config_factory->get('stripe_registration.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->stripeApi = $stripe_api;
  }

  /**
   * Check if a given user has a stripe subscription.
   *
   * @param \Drupal\user\UserInterface|\Drupal\Core\Session\AccountInterface $user
   *   The user.
   *
   * @return bool
   *  TRUE if the user has a subscription.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function userHasStripeSubscription($user): bool {
    $subscription = $this->loadLocalSubscription([
      'user_id' => $user->id(),
    ]);

    return (bool) $subscription;
  }

  /**
   * Loads a user's remote subscription.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return bool|\Stripe\Collection
   *   A collection of subscriptions.
   * @throws \Stripe\Error\Api
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemoteSubscriptionsByUser($user) {
    return $this->loadRemoteSubscriptionMultiple(['customer' => $user->stripe_customer_id->value]);
  }

  /**
   * Load multiple remote subscriptions.
   *
   * @param array $args
   *   Arguments by which to filter the subscriptions.
   *
   * @return bool|\Stripe\Collection
   *   A collection of subscriptions.
   * @throws \Stripe\Error\Api
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemoteSubscriptionMultiple($args = []) {
    // @todo add try, catch.
    $subscriptions = Subscription::all($args);

    if (!count($subscriptions->data)) {
      return FALSE;
    }

    return $subscriptions;
  }

  /**
   * Load a local subscription.
   *
   * @param array $properties
   *   Local properties by which to filter the subscriptions.
   *
   * @return \Drupal\stripe_registration\Entity\StripeSubscriptionEntity|bool
   *   A Stripe subscription entity, or else FALSE.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalSubscription($properties = []) {
    $stripe_subscription_entities = $this->loadLocalSubscriptionMultiple($properties);
    if (!count($stripe_subscription_entities)) {
      return FALSE;
    }

    $first = reset($stripe_subscription_entities);

    return $first;
  }

  /**
   * Load multiple local subscriptions.
   *
   * @param array $properties
   *   Local properties by which to filter the subscriptions.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of Stripe subscription entity.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalSubscriptionMultiple($properties = []) {
    $stripe_subscription_entities = $this->entityTypeManager
      ->getStorage('stripe_subscription')
      ->loadByProperties($properties);

    return $stripe_subscription_entities;
  }

  /**
   * Load multiple local plans.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects indexed by their IDs. Returns an empty array
   *   if no matching entities are found.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalPlanMultiple() {
    $stripe_plan_entities = $this->entityTypeManager
      ->getStorage('stripe_plan')
      ->loadMultiple();

    return $stripe_plan_entities;
  }

  /**
   * @param array $properties
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadLocalPlan($properties = []) {
    $stripe_plan_entities = $this->entityTypeManager
      ->getStorage('stripe_plan')
      ->loadByProperties($properties);

    if (!count($stripe_plan_entities)) {
      return FALSE;
    }

    $first = reset($stripe_plan_entities);

    return $first;
  }

  /**
   * Load multiple remote plans.
   *
   * @param array $args
   *   An array of arguments by which to filter the remote plans.
   *
   * @return \Stripe\Plan[]
   * @throws \Stripe\Error\Api
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemotePlanMultiple($args = []): array {
    $plans = Plan::all($args);

    // @todo handle no results case.
    // Re-key array.
    $keyed_plans = [];
    foreach ($plans->data as $plan) {
      $product = Product::retrieve($plan->product);
      $plan->name = $product->name;
      $keyed_plans[$plan->product] = $plan;
    }

    return $keyed_plans;
  }

  /**
   * @param $plan_id
   *
   * @return
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function loadRemotePlanById($plan_id) {
    $plan = $this->loadRemotePlanMultiple(['id' => $plan_id]);

    return $plan->data;
  }

  /**
   * @param bool $delete
   *   If true, local plans without matching remote plans will be deleted from Drupal.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function syncPlans($delete = FALSE): void {
    // @todo Handle pagination here.
    $remote_plans = $this->loadRemotePlanMultiple();
    $local_plans = $this->entityTypeManager->getStorage('stripe_plan')->loadMultiple();

    /** @var \Drupal\Core\Entity\EntityInterface[] $local_plans_keyed */
    $local_plans_keyed = [];
    foreach ($local_plans as $local_plan) {
      $local_plans_keyed[$local_plan->plan_id->value] = $local_plan;
    }

    $plans_to_delete = array_diff(array_keys($local_plans_keyed), array_keys($remote_plans));
    $plans_to_create = array_diff(array_keys($remote_plans), array_keys($local_plans_keyed));
    $plans_to_update = array_intersect(array_keys($remote_plans), array_keys($local_plans_keyed));

    $this->logger->info('Synchronizing Stripe plans.');

    // Create new plans.
    foreach ($plans_to_create as $plan_id) {
      $data = [
        'plan_id' => $remote_plans[$plan_id]->product,
        'plan_price_id' => $remote_plans[$plan_id]->id,
        'name' => $remote_plans[$plan_id]->name,
        'livemode' => $remote_plans[$plan_id]->livemode == 'true',
        'data' => serialize($remote_plans[$plan_id]),
      ];
      $this->entityTypeManager->getStorage('stripe_plan')->create($data)->save();
      $this->logger->info('Created @plan_id plan.', ['@plan_id' => $plan_id]);
    }
    // Delete invalid plans.
    if ($delete && $plans_to_delete) {
      $entities_to_delete = [];
      foreach ($plans_to_delete as $plan_id) {
        $entities_to_delete[] = $local_plans_keyed[$plan_id];
      }
      $this->entityTypeManager->getStorage('stripe_plan')
        ->delete($entities_to_delete);
      $this->logger->info('Deleted plans @plan_ids.', ['@plan_ids' => $plans_to_delete]);
    }
    // Update existing plans.
    foreach ($plans_to_update as $plan_id) {
      /** @var \Drupal\Core\Entity\EntityInterface $plan */
      $plan = $local_plans_keyed[$plan_id];
      /** @var \Stripe\Plan $remote_plan */
      $remote_plan = $remote_plans[$plan_id];
      $plan->set('name', $remote_plan->name);
      $plan->set('plan_price_id', $remote_plan->id);
      $plan->set('livemode', $remote_plan->livemode == 'true');
      $data = serialize($remote_plan->jsonSerialize());
      $plan->set('data', $data);
      $plan->save();
      $this->logger->info('Updated @plan_id plan.', ['@plan_id' => $plan_id]);
    }

    $this->messenger()->addMessage(t('Stripe plans were synchronized. Visit %link to see synchronized plans. If any plans were newly created, you must assign roles to them.', ['%link' => Link::fromTextAndUrl('Stripe plan list', Url::fromUri('internal:/admin/structure/stripe-registration/stripe-plan'))->toString()]), 'status');
  }

  /**
   * @param $remote_id
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function syncRemoteSubscriptionToLocal($remote_id): void {
    $remote_subscription = Subscription::retrieve($remote_id);
    $local_subscription = $this->loadLocalSubscription(['subscription_id' => $remote_id]);
    if (!$local_subscription) {
      throw new \RuntimeException("Could not find matching local subscription for Remote ID $remote_id.");
    }
    $local_subscription->updateFromUpstream($remote_subscription);
    $this->logger->info('Updated subscription entity #@subscription_id: @sub', ['@subscription_id' => $local_subscription->id(), '@sub' => $local_subscription->id()]);
  }

  /**
   * @param \Stripe\Subscription $remote_subscription
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Stripe\Subscription
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function createLocalSubscription($remote_subscription) {
    if ($user_entity = $this->loadUserByStripeCustomerId($remote_subscription->customer)) {
      $uid = $user_entity->id();
    }
    else {
      $customer = Customer::retrieve($remote_subscription->customer);
      $user_entity = user_load_by_mail($customer->email);
      $uid = $user_entity->id();
      $this->setLocalUserCustomerId($uid, $remote_subscription->customer);
      if (!$user_entity) {
        throw new \RuntimeException("There is no local user with with Stripe customer id " . $remote_subscription->customer);
      }
    }

    $values = [
      'user_id' => $uid,
      'plan_id' => $remote_subscription->plan->product,
      'plan_price_id' => $remote_subscription->plan->id,
      'subscription_id' => $remote_subscription->id,
      'customer_id' => $remote_subscription->customer,
      'status' => $remote_subscription->status,
      'roles' => [],
      'current_period_end' => ['value' =>  DrupalDateTime::createFromTimestamp($remote_subscription->current_period_end)->format('U')],
    ];
    $subscription = $this->entityTypeManager->getStorage('stripe_subscription')->create($values);
    $subscription->save();
    $this->logger->info('Created subscription entity #@subscription_id : @sub', ['@subscription_id' => $subscription->id(), '@sub' => json_encode($subscription)]);

    return $subscription;
  }

  /**
   * Sets the stripe_customer_id field value for a given user.
   *
   * @param $uid
   * @param string $customer_id
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function setLocalUserCustomerId($uid, $customer_id): void {
    /** @var \Stripe\Customer $user */
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $user->set('stripe_customer_id', $customer_id);
    $user->save();
  }

  /**
   * @param string|int $uid
   *
   * @return
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLocalUserCustomerId($uid) {
    /** @var \Stripe\Customer $user */
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    return $user->get('stripe_customer_id')->value;
  }

  /**
   * @param $customer_id
   *
   * @return bool|\Drupal\user\Entity\User
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function loadUserByStripeCustomerId($customer_id) {
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties(
      ['stripe_customer_id' => $customer_id],
    );

    if (!count($users)) {
      return FALSE;
    }

    return reset($users);
  }

}

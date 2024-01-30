<?php

namespace Drupal\stripe_registration\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Stripe\Checkout\Session;
use Stripe\Plan;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\stripe_registration\StripeRegistrationService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserSubscriptionsController.
 *
 * @package Drupal\stripe_registration\Controller
 */
class UserSubscriptionsController extends ControllerBase {

  /**
   * Drupal\stripe_registration\StripeRegistrationService definition.
   *
   * @var \Drupal\stripe_registration\StripeRegistrationService
   */
  protected $stripeRegistration;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * UserSubscriptionsController constructor.
   *
   * @param \Drupal\stripe_registration\StripeRegistrationService $stripe_registration
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   */
  public function __construct(StripeRegistrationService $stripe_registration, LoggerChannelInterface $logger, AccountProxyInterface $current_user) {
    $this->stripeRegistration = $stripe_registration;
    $this->logger = $logger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('stripe_registration.stripe_api'),
      $container->get('logger.channel.stripe_registration'),
      $container->get('current_user')
    );
  }

  /**1
   * Redirect.
   *
   * @return string
   *   Return Hello string.
   */
  public function redirectToSubscriptions() {
    return $this->redirect('stripe_registration.manage_billing', ['user' => $this->currentUser()->id()]);
  }

  /**
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see \Drupal\stripe_registration\Plugin\Menu\SubscribeMenuLink::getTitle()
   */
  public function subscribeTitle() {
    if ($this->stripeRegistration->userHasStripeSubscription($this->currentUser())) {
      return 'Upgrade';
    }
    return 'Subscribe';
  }

  /**
   * @return array
   *   Return
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function subscribe(): array {
    $remote_plans = $this->stripeRegistration->loadRemotePlanMultiple();
    $user = User::load($this->currentUser->id());
    $build = [
      '#theme' => 'stripe_subscribe_plans',
      '#plans'=> [],
    ];
    foreach ($remote_plans as $plan) {
      $element = [
        '#theme' => 'stripe_subscribe',
        '#plan' => [
          // This is the price_id, which does not match the local plan->id (product).
          'price_id' => $plan->id,
          'name' => $plan->name,
        ],
        '#remote_plan' => $plan,
        '#plan_entity' => $this->stripeRegistration->loadLocalPlan(['plan_price_id' => $plan->id]),
        // @tode Check $subscription->cancel_at_period_end.
        '#current_user_subscribes_to_any_plan' => $this->stripeRegistration->userHasStripeSubscription($user),
        // @tode Check $subscription->cancel_at_period_end.
        '#current_user_subscribes_to_this_plan' => $this->userIsSubscribedToPlan($user, $plan),
        '#attached' => [
          'library' => [
            'stripe_registration/checkout',
            'stripe_registration/stripe.stripejs',
            'core/drupal.dialog.ajax',
          ],
        ],
      ];

      $build['#plans'][$plan->id] = $element;
    }

    return $build;
  }

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   * @throws \Exception
   */
  public function createSubscribeSession(Request $request): Response {
    // Simply instantiating the service will configure Stripe with the correct API key.
    /** @var \Drupal\stripe_api\StripeApiService $stripe_api */
    $stripe_api =  \Drupal::service('stripe_api.stripe_api');

    if ($request->get('return_url')) {
      $success_url = Url::fromUri('internal:/' . $request->get('return_url'), ['absolute' => TRUE, 'query' => ['checkout' => 'success']])->toString();
      $cancel_url = Url::fromUri('internal:/' . $request->get('return_url'), ['absolute' => TRUE, 'query' => ['checkout' => 'failure']])->toString();
    }
    else {
      $success_url = Url::fromRoute('<front>', [], ['absolute' => TRUE, 'query' => ['checkout' => 'success']])->toString();
      $cancel_url = Url::fromRoute('<front>', [], ['absolute' => TRUE, 'query' => ['checkout' => 'failure']])->toString();
    }

    $params = [
      'payment_method_types' => ['card'],
      'line_items' => [
        [
          'quantity' => 1,
          // This must correspond to an existing price id in the Stripe backend.
          'price' => $request->get('price_id'),
        ],
      ],
      'mode' => 'subscription',
      'subscription_data' => [
        'trial_from_plan' => TRUE,
      ],
      'metadata' => [
        'module' => 'stripe_registration',
        'uid' => $this->currentUser()->id(),
      ],
      'success_url' => $success_url,
      'cancel_url' => $cancel_url,
    ];
    if ($customer_id = $this->stripeRegistration->getLocalUserCustomerId($this->currentUser()->id())) {
      $params['customer'] = $customer_id;
    }
    else {
      $params['customer_email'] = $this->currentUser()->getEmail();
    }

    try {
      $session = Session::create($params);
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
    }

    $response_content = [
      'session_id' => $session->id,
      'public_key' => \Drupal::service('stripe_api.stripe_api')->getPubKey(),
    ];

    return new Response(json_encode($response_content), Response::HTTP_ACCEPTED);
  }

  /**
   * @return \Drupal\Core\Access\AccessResult
   *   Return
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function manageBillingAccess($user) {
    return AccessResult::allowedIf(
      $this->stripeRegistration->userHasStripeSubscription(User::load($user))
    );
  }

  /**
   * @param $user
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   */
  public function manageBilling($user) {
    try {
      $customer_id = $this->stripeRegistration->getLocalUserCustomerId($user);
      $return_url = Url::fromRoute('<front>', [], ['absolute' => TRUE]);
      // This was not fun.
      // @see https://www.drupal.org/node/2630808
      // @see https://drupal.stackexchange.com/questions/225956/cache-controller-with-json-response
      // @see https://www.lullabot.com/articles/early-rendering-a-lesson-in-debugging-drupal-8
      $return_url_string = $return_url->toString(TRUE)->getGeneratedUrl();
      $session = \Stripe\BillingPortal\Session::create([
        'customer' => $customer_id,
        'return_url' => $return_url_string,
      ]);
    }
    catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return [
        '#markup' => 'Something went wrong! ' . $exception->getMessage(),
      ];
    }

    return new TrustedRedirectResponse($session->url);
  }

  /**
   * @param UserInterface $user
   * @param \Stripe\Plan $plan
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function userIsSubscribedToPlan($user, Plan $plan): bool {
    if ($this->stripeRegistration->userHasStripeSubscription($user)) {
      $subscription = $this->stripeRegistration->loadLocalSubscription([
        'user_id' => $this->currentUser->id(),
      ]);
      return $subscription->plan_price_id->value === $plan->id;
    }

    return FALSE;
  }

}

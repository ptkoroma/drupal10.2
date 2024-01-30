<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_stripe\ErrorHelper;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe\Event\PaymentIntentEvent;
use Drupal\commerce_stripe\Event\PaymentMethodCreateEvent;
use Drupal\commerce_stripe\Event\StripeEvents;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Stripe\Balance;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\Stripe as StripeLibrary;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Stripe Payment Element payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "stripe_payment_element",
 *   label = "Stripe Payment Element",
 *   display_label = "Stripe Payment Element",
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa", "unionpay"
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class StripePaymentElement extends OffsitePaymentGatewayBase implements StripePaymentElementInterface {

  /**
   * Payment source for use in payment intent metadata.
   */
  const PAYMENT_SOURCE = 'Drupal';

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->logger = $container->get('logger.channel.commerce_stripe');
    $instance->uuidService = $container->get('uuid');
    $instance->renderer = $container->get('renderer');
    $instance->currentUser = $container->get('current_user');
    $instance->init();

    return $instance;
  }

  /**
   * Re-initializes the SDK after the plugin is unserialized.
   */
  public function __wakeup() {
    parent::__wakeup();

    $this->init();
  }

  /**
   * Initializes the SDK.
   */
  protected function init() {
    $extension_info = $this->moduleExtensionList->getExtensionInfo('commerce_stripe');
    $version = !empty($extension_info['version']) ? $extension_info['version'] : '8.x-1.0-dev';
    StripeLibrary::setAppInfo('Drupal Commerce by Centarro', $version, 'https://www.drupal.org/project/commerce_stripe', 'pp_partner_Fa3jTqCJqTDtHD');

    // If Drupal is configured to use a proxy for outgoing requests, make sure
    // that the proxy CURLOPT_PROXY setting is passed to the Stripe SDK client.
    $http_client_config = Settings::get('http_client_config');
    if (!empty($http_client_config['proxy']['https'])) {
      $curl = new CurlClient([CURLOPT_PROXY => $http_client_config['proxy']['https']]);
      ApiRequestor::setHttpClient($curl);
    }

    StripeLibrary::setApiKey($this->configuration['secret_key']);
    StripeLibrary::setApiVersion('2019-12-03');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'publishable_key' => '',
      'secret_key' => '',
      'payment_method_usage' => 'on_session',
      'style' => [],
      'checkout_form_display_label' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['publishable_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publishable key'),
      '#default_value' => $this->configuration['publishable_key'],
      '#required' => TRUE,
    ];
    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key'),
      '#default_value' => $this->configuration['secret_key'],
      '#required' => TRUE,
    ];
    $form['validate_api_keys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate API keys upon form submission.'),
      '#default_value' => TRUE,
    ];
    $form['payment_method_usage'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method usage'),
      '#options' => [
        'on_session' => $this->t('On-session: the customer will always initiate payments in checkout'),
        'off_session' => $this->t("Off-session or mixed: the site may process payments on the customer's behalf (e.g., recurring billing)"),
      ],
      '#empty_value' => '',
      '#description' => $this->t('This value will be passed as the setup_future_usage parameter in your payment intents.'),
      '#default_value' => $this->configuration['payment_method_usage'],
    ];
    $form['style'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Style settings'),
      '#description' => $this->t('Preview the options in the <a href=":url1" target="_blank">Payment Element</a> homepage or read more in the <a href=":url2" target="_blank">Elements Appearance API</a> documentation.', [
        ':url1' => 'https://stripe.com/docs/payments/payment-element',
        ':url2' => 'https://stripe.com/docs/elements/appearance-api'
      ]),
    ];
    $form['style']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => [
        'stripe' => $this->t('Stripe'),
        'night' => $this->t('Night'),
        'flat' => $this->t('Flat'),
      ],
      '#default_value' => $this->configuration['style']['theme'] ?? 'stripe',
    ];
    $form['style']['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'tabs' => $this->t('Tabs'),
        'accordion' => $this->t('Accordion'),
      ],
      '#default_value' => $this->configuration['style']['layout'] ?? 'tabs',
    ];
    $form['checkout_form_display_label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Checkout form display label'),
    ];
    $form['checkout_form_display_label']['custom_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom display label'),
      '#description' => $this->t('Defaults to <em>Credit card</em>. Use a space character to show no text label beside the logos.'),
      '#default_value' => $this->configuration['checkout_form_display_label']['custom_label'],
    ];
    $form['checkout_form_display_label']['show_payment_method_logos'] = [
      '#type' => 'select',
      '#title' => $this->t('Show payment method logos?'),
      '#options' => [
        'no' => $this->t('No'),
        'after' => $this->t('After the label'),
        'before' => $this->t('Before the label'),
      ],
      '#default_value' => $this->configuration['checkout_form_display_label']['show_payment_method_logos'] ?? 'no',
    ];
    $default_credit_cards = ['visa', 'mastercard', 'amex', 'discover'];
    $supported_credit_cards = [];
    foreach ($this->getCreditCardTypes() as $credit_card) {
      $supported_credit_cards[$credit_card->getId()] = $credit_card->getLabel();
      if (!isset($default_credit_cards[$credit_card->getId()])) {
        unset($default_credit_cards[$credit_card->getId()]);
      }
    }
    // Adds wallets to the list.
    $supported_credit_cards += [
      'applepay' => $this->t('Apple Pay'),
      'googlepay' => $this->t('Google Pay'),
    ];
    $form['checkout_form_display_label']['include_logos'] = [
      '#title' => $this->t('Logos to include'),
      '#type' => 'checkboxes',
      '#options' => $supported_credit_cards,
      '#default_value' => $this->configuration['checkout_form_display_label']['include_logos'] ?? $default_credit_cards,
      '#states' => [
        'invisible' => [
          ':input[name="configuration[' . $this->pluginId . '][checkout_form_display_label][show_payment_method_logos]"]' => ['value' => 'no'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      // Validate the secret key.
      $expected_livemode = $values['mode'] == 'live';
      if (!empty($values['secret_key']) && $values['validate_api_keys']) {
        try {
          StripeLibrary::setApiKey($values['secret_key']);
          // Make sure we use the right mode for the secret keys.
          if (Balance::retrieve()->offsetGet('livemode') !== $expected_livemode) {
            $form_state->setError($form['secret_key'], $this->t('The provided secret key is not for the selected mode (@mode).', ['@mode' => $values['mode']]));
          }
        }
        catch (ApiErrorException $e) {
          $form_state->setError($form['secret_key'], $this->t('Invalid secret key.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['publishable_key'] = $values['publishable_key'];
      $this->configuration['secret_key'] = $values['secret_key'];
      $this->configuration['payment_method_usage'] = $values['payment_method_usage'];
      $this->configuration['style'] = $values['style'];
      $this->configuration['checkout_form_display_label'] = $values['checkout_form_display_label'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    assert($payment_method instanceof PaymentMethodInterface);
    $this->assertPaymentMethod($payment_method);
    $order = $payment->getOrder();
    assert($order instanceof OrderInterface);
    $intent_id = $order->getData('stripe_intent');
    try {
      if (!empty($intent_id)) {
        $intent = PaymentIntent::retrieve($intent_id);
      }
      else {
        // If there is no payment intent, it means we are not in a checkout
        // flow with the stripe review pane, so we should assume the
        // customer is not available for SCA and create an immediate
        // off_session payment intent.
        $intent_attributes = [
          'confirm'        => TRUE,
          'off_session'    => TRUE,
          'capture_method' => $capture ? 'automatic' : 'manual',
        ];
        $intent = $this->createPaymentIntent($order, $intent_attributes, $payment);
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_CONFIRMATION) {
        $intent = $intent->confirm();
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_ACTION) {
        throw new SoftDeclineException('The payment intent requires action by the customer for authentication');
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD) {
        throw new SoftDeclineException('The payment intent requires payment method');
      }
      if (!in_array($intent->status, [PaymentIntent::STATUS_REQUIRES_CAPTURE, PaymentIntent::STATUS_SUCCEEDED], TRUE)) {
        $order->set('payment_method', NULL);
        $this->deletePaymentMethod($payment_method);
        if ($intent->status === PaymentIntent::STATUS_CANCELED) {
          $order->setData('stripe_intent', NULL);
        }

        if (is_object($intent->last_payment_error)) {
          $error = $intent->last_payment_error;
          $decline_message = sprintf('%s: %s', $error->type, $error->message ?? '');
        }
        else {
          $decline_message = $intent->last_payment_error;
        }
        throw new HardDeclineException($decline_message);
      }
      if (count($intent->charges->data) === 0) {
        throw new HardDeclineException(sprintf('The payment intent %s did not have a charge object.', $intent->id));
      }
      // Keep the payment in the new status if it has not yet been processed.
      if ($intent->status !== PaymentIntent::STATUS_PROCESSING) {
        $next_state = $capture ? 'completed' : 'authorization';
        $payment->setState($next_state);
      }
      $payment->setRemoteId($intent->id);
      $payment->save();

      // Add metadata and extra transaction data where required.
      $event = new PaymentIntentEvent($order, [], $payment);
      $this->eventDispatcher->dispatch($event, StripeEvents::PAYMENT_INTENT_CREATE);
      // Update the transaction data from additional information added through
      // the event.
      $intent_array = $event->getIntentAttributes();
      PaymentIntent::update($intent->id, $intent_array);

      $order->unsetData('stripe_intent');
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $query = $request->query->all();
    $intent_id = $order->getData('stripe_intent');

    if (empty($query['payment_intent'])) {
      throw new PaymentGatewayException('The payment_intent parameter is missing for this transaction.');
    }

    $intent = PaymentIntent::retrieve($query['payment_intent']);
    if (empty($intent) || $intent->id !== $intent_id) {
      throw new PaymentGatewayException('The payment intent is missing or invalid.');
    }

    if (!in_array($intent->status, [PaymentIntent::STATUS_SUCCEEDED, PaymentIntent::STATUS_PROCESSING, PaymentIntent::STATUS_REQUIRES_CAPTURE])) {
      throw new PaymentGatewayException(sprintf('Unexpected payment intent status %s.', $intent->status));
    }

    // Create a payment method.
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    $payment_method = $payment_method_storage->createForCustomer(
      'credit_card',
      $this->parentEntity->id(),
      $order->getCustomerId(),
      $order->getBillingProfile()
    );

    $payment_details = ['stripe_payment_method_id' => $intent->payment_method];
    $this->createPaymentMethod($payment_method, $payment_details);
    $order->set('payment_method', $payment_method);
  }

  /**
   * {@inheritdoc}
   */
  public function onNotify(Request $request) {
    // Reacts to Webhook events.
    $request_body = Json::decode($request->getContent());

    $supported_events = [
      'payment_intent.succeeded',
      'payment_intent.canceled',
      'charge.refunded',
    ];

    // Ignore unsupported events.
    if (!isset($request_body['type']) ||
      !in_array($request_body['type'], $supported_events)) {
      return;
    }

    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $object = $request_body['data']['object'];

    switch ($request_body['type']) {
      case 'charge.refunded':
        $latest_refund = reset($object['refunds']['data']);
        // Ignore the request as it was made from Drupal.
        if (isset($latest_refund['metadata']['refund_source']) &&
          $latest_refund['metadata']['refund_source'] == self::PAYMENT_SOURCE) {
          return;
        }

        // Ignore the request if amount_captured is 0.
        if (!((int) $object['amount_captured'])) {
          return;
        }

        $payment = $payment_storage->loadByRemoteId($latest_refund['payment_intent']);
        if (!$payment) {
          /*$this->logger->notice('Webhook for order ID @order_id ignored: the transaction to be refunded does not exist.', [
            '@order_id' => $object['metadata']['order_id'],
          ]);*/
          return;
        }

        // Calculate the refund amount.
        $refund_amount = $this->minorUnitsConverter->fromMinorUnits(
          $object['amount_refunded'] - ($object['amount'] - $object['amount_captured']),
          strtoupper($object['currency'])
        );
        if ($refund_amount->lessThan($payment->getAmount())) {
          $transition_id = 'partially_refund';
        }
        else {
          $transition_id = 'refund';
        }
        if ($payment->getState()->isTransitionAllowed($transition_id)) {
          $payment->getState()->applyTransitionById($transition_id);
        }
        $payment->setRefundedAmount($refund_amount);
        $payment->save();
        break;

      case 'payment_intent.canceled':
        // Ignore the request as it was made from Drupal.
        if (isset($object['metadata']['void_source']) &&
          $object['metadata']['void_source'] == self::PAYMENT_SOURCE) {
          return;
        }

        $payment = $payment_storage->loadByRemoteId($object['id']);
        if (!$payment) {
          /*$this->logger->notice('Webhook for order ID @order_id ignored: no payment transaction found.', [
            '@order_id' => $object['metadata']['order_id'],
          ]);*/
          return;
        }

        // Void the payment if the authorization has been voided.
        if ($payment->getState()->isTransitionAllowed('void')) {
          $payment->getState()->applyTransitionById('void');
          $payment->save();
        }
        break;

      case 'payment_intent.succeeded':
        // Ignore the request as it was made from Drupal.
        if (isset($object['metadata']['capture_source']) &&
          $object['metadata']['capture_source'] == self::PAYMENT_SOURCE) {
          return;
        }

        $payment = $payment_storage->loadByRemoteId($object['id']);
        if (!$payment) {
          /*$this->logger->notice('Webhook for order ID @order_id ignored: no payment transaction found.', [
            '@order_id' => $object['metadata']['order_id'],
          ]);*/
          return;
        }

        // Complete the payment if authorization is captured.
        if ($payment->getState()->getId() == 'authorization') {
          $amount = $this->minorUnitsConverter->fromMinorUnits(
            $object['amount_received'],
            strtoupper($object['currency'])
          );
          $payment->setAmount($amount);
          $payment->getState()->applyTransitionById('capture');
          $payment->save();
        }

        // Complete the payment that was in processing status.
        if ($payment->getState()->getId() == 'new') {
          $amount = $this->minorUnitsConverter->fromMinorUnits(
            $object['amount_received'],
            strtoupper($object['currency'])
          );
          $payment->setAmount($amount);
          $payment->getState()->applyTransitionById('authorize_capture');
          $payment->save();
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    try {
      $intent_id = $payment->getRemoteId();
      $intent = PaymentIntent::retrieve($intent_id);

      $amount_to_capture = $this->minorUnitsConverter->toMinorUnits($amount);

      if ($intent->status == 'requires_capture') {
        $intent = $intent->capture([
          'amount_to_capture' => $amount_to_capture,
          'metadata' => [
            'capture_source' => self::PAYMENT_SOURCE,
            'capture_uid' => $this->currentUser->id(),
          ],
        ]);
      }

      if ($intent->status == 'succeeded') {
        // Log a warning to the watchdog if the amount received was unexpected.
        if ($intent->amount_received != $amount_to_capture) {
          // Set the payment amount to what was actually received.
          $received = $this->minorUnitsConverter->fromMinorUnits($intent->amount_received, $amount->getCurrencyCode());
          $payment->setAmount($received);

          \Drupal::logger('commerce_stripe')->warning($this->t('Attempted to capture @amount but received @received.', ['@amount' => (string) $amount, '@received' => (string) $received]));
        }
        else {
          $payment->setAmount($amount);
        }

        $payment->setState('completed');
        $payment->save();
      }
      else {
        throw new PaymentGatewayException('Only requires_capture PaymentIntents can be captured.');
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    // Void Stripe payment - release uncaptured payment.
    try {
      $intent_id = $payment->getRemoteId();
      $intent = PaymentIntent::retrieve($intent_id);

      $statuses_to_void = [
        'requires_payment_method',
        'requires_capture',
        'requires_confirmation',
        'requires_action',
      ];
      if (!in_array($intent->status, $statuses_to_void)) {
        throw new PaymentGatewayException('The PaymentIntent cannot be voided.');
      }
      $intent = PaymentIntent::update($intent->id, [
        'metadata' => [
          'void_source' => self::PAYMENT_SOURCE,
          'void_uid' => $this->currentUser->id(),
        ],
      ]);
      $intent->cancel();

      $payment->setState('authorization_voided');
      $payment->save();
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    try {
      $intent_id = $payment->getRemoteId();

      $data = [
        'amount' => $this->minorUnitsConverter->toMinorUnits($amount),
        'payment_intent' => $intent_id,
        'metadata' => [
          'refund_source' => self::PAYMENT_SOURCE,
          'refund_uid' => $this->currentUser->id(),
        ],
      ];

      $refund = Refund::create($data, [
        'idempotency_key' => $this->uuidService->generate(),
      ]);
      ErrorHelper::handleErrors($refund);

      $old_refunded_amount = $payment->getRefundedAmount();
      $new_refunded_amount = $old_refunded_amount->add($amount);
      if ($new_refunded_amount->lessThan($payment->getAmount())) {
        $payment->setState('partially_refunded');
      }
      else {
        $payment->setState('refunded');
      }

      $payment->setRefundedAmount($new_refunded_amount);
      $payment->save();
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
  }

  /**
   * Creates a payment method with the given payment details.
   *
   * See onReturn() for more details.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details provided by the payment method form
   *   for on-site gateways, or the incoming request for off-site gateways.
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'stripe_payment_method_id',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new InvalidRequestException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // Allow alteration of the payment method before remote creation.
    $event = new PaymentMethodCreateEvent($payment_method, $payment_details);
    $this->eventDispatcher->dispatch($event, StripeEvents::PAYMENT_METHOD_CREATE);

    $remote_payment_method = $this->doCreatePaymentMethod($payment_method, $payment_details);
    $payment_method->card_type = $this->mapCreditCardType($remote_payment_method['brand']);
    $payment_method->card_number = $remote_payment_method['last4'];
    $payment_method->card_exp_month = $remote_payment_method['exp_month'];
    $payment_method->card_exp_year = $remote_payment_method['exp_year'];
    $expires = CreditCard::calculateExpirationTimestamp($remote_payment_method['exp_month'], $remote_payment_method['exp_year']);
    $payment_method->setRemoteId($payment_details['stripe_payment_method_id']);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record.
    $payment_method_remote_id = $payment_method->getRemoteId();
    try {
      $remote_payment_method = PaymentMethod::retrieve($payment_method_remote_id);
      if ($remote_payment_method->customer) {
        $remote_payment_method->detach();
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
    $payment_method->delete();
  }

  /**
   * Creates the payment method on the gateway.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return array
   *   The payment method information returned by the gateway. Notable keys:
   *   - token: The remote ID.
   *   Credit card specific keys:
   *   - card_type: The card type.
   *   - last4: The last 4 digits of the credit card number.
   *   - expiration_month: The expiration month.
   *   - expiration_year: The expiration year.
   */
  protected function doCreatePaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $stripe_payment_method_id = $payment_details['stripe_payment_method_id'];
    $owner = $payment_method->getOwner();
    $customer_id = NULL;
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);
    }
    try {
      $stripe_payment_method = PaymentMethod::retrieve($stripe_payment_method_id);
      if ($customer_id) {
        $stripe_payment_method->attach(['customer' => $customer_id]);
        $email = $owner->getEmail();
      }
      // If the user is authenticated, created a Stripe customer to attach the
      // payment method to.
      elseif ($owner && $owner->isAuthenticated()) {
        $email = $owner->getEmail();
        $customer = Customer::create([
          'email' => $email,
          'description' => $this->t('Customer for :mail', [':mail' => $email]),
          'payment_method' => $stripe_payment_method_id,
        ]);
        $customer_id = $customer->id;
        $this->setRemoteCustomerId($owner, $customer_id);
        $owner->save();
      }
      else {
        $email = NULL;
      }

      if ($customer_id && $email) {
        $payment_method_data = [
          'email' => $email,
        ];
        if ($billing_profile = $payment_method->getBillingProfile()) {
          $billing_address = $billing_profile->get('address')->first()->toArray();
          $payment_method_data['address'] = [
            'city' => $billing_address['locality'] ?? '',
            'country' => $billing_address['country_code'] ?? '',
            'line1' => $billing_address['address_line1'] ?? '',
            'line2' => $billing_address['address_line2'] ?? '',
            'postal_code' => $billing_address['postal_code'] ?? '',
            'state' => $billing_address['administrative_area'] ?? '',
          ];
          $name_parts = [];
          foreach (['given_name', 'family_name'] as $name_key) {
            if (!empty($billing_address[$name_key])) {
              $name_parts[] = $billing_address[$name_key];
            }
          }
          $payment_method_data['name'] = implode(' ', $name_parts);
        }
        PaymentMethod::update($stripe_payment_method_id, ['billing_details' => $payment_method_data]);
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
    return $stripe_payment_method->card;
  }

  /**
   * Maps the Stripe credit card type to a Commerce credit card type.
   *
   * @param string $card_type
   *   The Stripe credit card type.
   *
   * @return string
   *   The Commerce credit card type.
   */
  protected function mapCreditCardType($card_type) {
    $map = [
      'amex' => 'amex',
      'diners' => 'dinersclub',
      'discover' => 'discover',
      'jcb' => 'jcb',
      'mastercard' => 'mastercard',
      'visa' => 'visa',
      'unionpay' => 'unionpay',
    ];
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentIntent(OrderInterface $order, $intent_attributes = [], PaymentInterface $payment = NULL) {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment ? $payment->getPaymentMethod() : $order->get('payment_method')->entity;
    /** @var \Drupal\commerce_price\Price */
    $amount = $payment ? $payment->getAmount() : $order->getTotalPrice();

    $default_intent_attributes = [
      'amount' => $this->minorUnitsConverter->toMinorUnits($amount),
      'currency' => strtolower($amount->getCurrencyCode()),
      'metadata' => [
        'order_id' => $order->id(),
        'store_id' => $order->getStoreId(),
      ],
      'capture_method' => 'automatic',
      'automatic_payment_methods' => [
        'enabled' => TRUE,
      ],
    ];

    $profiles = $order->collectProfiles();
    if (isset($profiles['shipping']) && !$profiles['shipping']->get('address')->isEmpty()) {
      $address = $profiles['shipping']->get('address')->first()->toArray();
      $default_intent_attributes['shipping'] = [
        'name' => $address['given_name'] . ' ' . $address['family_name'],
        'address' => [
          'city' => $address['locality'],
          'country' => $address['country_code'],
          'line1' => $address['address_line1'],
          'line2' => $address['address_line2'],
          'postal_code' => $address['postal_code'],
          'state' => $address['administrative_area'],
        ],
      ];
    }

    if ($payment_method) {
      $default_intent_attributes['payment_method'] = $payment_method->getRemoteId();
    }

    $customer_remote_id = $this->getRemoteCustomerId($order->getCustomer());
    if (!empty($customer_remote_id)) {
      $default_intent_attributes['customer'] = $customer_remote_id;
    }

    $intent_array = NestedArray::mergeDeep($default_intent_attributes, $intent_attributes);

    // Add metadata and extra transaction data where required.
    $event = new PaymentIntentEvent($order, $intent_array);
    $this->eventDispatcher->dispatch($event, StripeEvents::PAYMENT_INTENT_CREATE);

    // Alter or extend the intent array from additional information added
    // through the event.
    $intent_array = $event->getIntentAttributes();

    try {
      $intent = PaymentIntent::create($intent_array);
      $order->setData('stripe_intent', $intent->id)->save();
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }
    return $intent;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublishableKey() {
    return $this->configuration['publishable_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSecretKey() {
    return $this->configuration['secret_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodUsage() {
    return $this->configuration['payment_method_usage'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutFormDisplayLabel() {
    return $this->configuration['checkout_form_display_label'];
  }

  /**
   * Gets checkout display label.
   *
   * @return string
   *   Checkout display label.
   */
  public function getCheckoutDisplayLabel() {
    $display_label = '';

    $display_settings = $this->getCheckoutFormDisplayLabel();
    if (empty($display_settings['custom_label'])) {
      return $display_label;
    }

    $display_label = $display_settings['custom_label'];
    if ($display_settings['show_payment_method_logos'] === 'no') {
      return $display_label;
    }

    $credit_card_logos = [
      '#theme' => 'commerce_stripe_credit_card_logos',
      '#credit_cards' => array_filter($display_settings['include_logos']),
    ];
    $before_logos = $after_logos = '';
    $payment_method_logos = $this->renderer->renderPlain($credit_card_logos);
    if ($display_settings['show_payment_method_logos'] === 'before') {
      $before_logos = $payment_method_logos;
    }
    else {
      $after_logos = $payment_method_logos;
    }

    return $before_logos . $display_label . $after_logos;
  }

}

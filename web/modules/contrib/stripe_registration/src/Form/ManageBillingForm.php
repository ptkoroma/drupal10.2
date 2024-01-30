<?php

namespace Drupal\stripe_registration\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\stripe_registration\StripeRegistrationService;
use Drupal\user\Entity\User;
use Stripe\Customer;

/**
 * Class ManageBillingForm.
 *
 * @package Drupal\stripe_registration\Form
 */
class ManageBillingForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stripe_manage_billing_form';
  }

  /**
   * {@inheritdoc}
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\stripe_registration\StripeRegistrationService $stripe_registration */
    $stripe_registration =  \Drupal::service('stripe_registration.stripe_api');

    $form['#title'] = $this->t('Manage Billing');

    // Bail out if the user doesn't have an active subscription.
    if (
      !$stripe_registration->getLocalUserCustomerId($this->currentUser()->id())
      || !$stripe_registration->loadRemoteSubscriptionsByUser(User::load($this->currentUser()->id()))
    ) {
      $form['#markup'] = 'You have no active subscriptions.';
      return $form;
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit']['#type'] = 'submit';
    $form['actions']['submit']['#button_type'] = 'primary';
    $button_text = $this->getButtonText($form_state, $stripe_registration);
    $form['actions']['submit']['#value'] = $button_text;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = \Drupal::messenger();
    try {
      // @todo Allow this to be done for a given user id, not just current user.
      $uid = \Drupal::currentUser()->id();
      $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);

      /** @var \Drupal\stripe_registration\StripeRegistrationService $stripe_registration */
      $stripe_registration =  \Drupal::service('stripe_registration.stripe_api');

      // Simply instantiating the service will configure Stripe with the correct API key.
      /** @var \Drupal\stripe_api\StripeApiService $stripe_api */
      $stripe_api =  \Drupal::service('stripe_api.stripe_api');

      $customer_id = $stripe_registration->getLocalUserCustomerId($uid);
      $return_url = Url::fromUri('internal:/' . \Drupal::request()->query->get('return_url'), ['absolute' => TRUE])->toString() ?: Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

      $session = \Stripe\BillingPortal\Session::create([
        'customer' => $customer_id,
        'return_url' => $return_url,
      ]);

      $response = new TrustedRedirectResponse(Url::fromUri($session->url)->toString());
      $form_state->setResponse($response);
    }
    catch (\Exception $e) {
      \Drupal::logger('stripe_registration')
        ->error(t("Could not subscribe user @uid, error:\n@stripe_error", [
          '@uid' => $form_state->getValue('uid'),
          '@stripe_error' => $e->getMessage(),
        ]));
      $messenger->addMessage(t('@stripe_error', [
        '@stripe_error' => $e->getMessage(),
      ]), 'error');
      $form_state->setErrorByName('stripe-messages', $e->getMessage());
    }

  }

  /**
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param \Drupal\stripe_registration\StripeRegistrationService $stripe_registration
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getButtonText(FormStateInterface $form_state, StripeRegistrationService $stripe_registration): string {
    if (array_key_exists(1, $form_state->getBuildInfo()['args']) && $form_state->getBuildInfo()['args'][1] !== NULL) {
      $button_text = $form_state->getBuildInfo()['args'][1];
    }
    elseif ($stripe_registration->userHasStripeSubscription(User::load(\Drupal::currentUser()
      ->id()))) {
      $button_text = 'Manage billing information';
    }

    return $button_text;
  }

}

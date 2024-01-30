<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2GrantType;

use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Url;
use Drupal\oauth2_client\Exception\AuthCodeRedirect;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginRedirectInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles Authorization Code Grants for the OAuth2 Client module..
 *
 * @Oauth2GrantType(
 *   id = "authorization_code",
 *   label = @Translation("Authorization Code Grant"),
 *   description = @Translation("Makes Auth Code grant requests.")
 * )
 */
class AuthorizationCode extends Oauth2GrantTypePluginBase {

  /**
   * The Drupal tempstore.
   */
  protected PrivateTempStore $tempStore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $tempstoreFactory */
    $tempstoreFactory = $container->get('tempstore.private');
    $instance->tempStore = $tempstoreFactory->get('oauth2_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(Oauth2ClientPluginInterface $clientPlugin): ?AccessTokenInterface {
    $provider = $clientPlugin->getProvider();
    // Get the authorization URL. This also generates the state.
    $authorization_url = $provider->getAuthorizationUrl();
    if (!empty($authorization_url)) {
      // Save the state to Drupal's tempstore.
      $this->tempStore->set('oauth2_client_state-' . $clientPlugin->getPluginId(), $provider->getState());
      if ($this->currentRequest->hasSession()) {
        // If we have a session, save before redirect.
        $this->currentRequest->getSession()->save();
      }
      // Redirect to the authorization URL.
      throw new AuthCodeRedirect($authorization_url);
    }
    return NULL;
  }

  /**
   * Executes an authorization_code grant request with the give code.
   *
   * @param \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $clientPlugin
   *   The Oauth2Client plugin using this grant.
   * @param string $code
   *   The authorization code.
   *
   * @return bool
   *   Was a valid token retrieved?
   */
  public function requestAccessToken(Oauth2ClientPluginInterface $clientPlugin, string $code): bool {
    $provider = $clientPlugin->getProvider();
    // There should not be a 'code' key in the options, ensure the parameter
    // value is used.
    $options = $clientPlugin->getRequestOptions(['code' => $code]);
    // Try to get an access token using the authorization code grant.
    try {
      $accessToken = $provider->getAccessToken('authorization_code', $options);
      if ($accessToken instanceof AccessTokenInterface) {
        $clientPlugin->storeAccessToken($accessToken);
        return TRUE;
      }
    }
    catch (IdentityProviderException $e) {
      watchdog_exception('OAuth2 Client', $e);
    }
    return FALSE;
  }

  /**
   * Provide a redirect for use following authorization code capture.
   *
   * @param \Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface $client
   *   The Oauth2Client plugin using this grant.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect provided by the plugin, or default to the list of config.
   */
  public function getPostCaptureRedirect(Oauth2ClientPluginInterface $client): RedirectResponse {
    if ($client instanceof Oauth2ClientPluginRedirectInterface) {
      return $client->getPostCaptureRedirect();
    }
    $url = Url::fromRoute('entity.oauth2_client.collection');
    return new RedirectResponse($url->toString(TRUE)->getGeneratedUrl());
  }

}

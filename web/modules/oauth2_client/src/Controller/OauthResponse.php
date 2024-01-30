<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\Url;
use Drupal\oauth2_client\Entity\Oauth2Client;
use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use Drupal\oauth2_client\Plugin\Oauth2GrantType\AuthorizationCode;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller to process an authorization code request.
 *
 * @package Drupal\oauth2_client\Controller
 */
class OauthResponse extends ControllerBase {

  /**
   * Injected service.
   */
  protected Request $currentRequest;

  /**
   * The route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Injected client service.
   */
  protected AuthorizationCode $grantPlugin;

  /**
   * The Drupal tempstore.
   */
  protected PrivateTempStore $tempstore;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    /** @var \Drupal\oauth2_client\PluginManager\Oauth2GrantTypePluginManager $pluginManager */
    $pluginManager = $container->get('plugin.manager.oauth2_grant_type');
    $instance->grantPlugin = $pluginManager->createInstance('authorization_code');
    $instance->messenger = $container->get('messenger');
    $instance->routeMatch = $container->get('current_route_match');
    $requestStack = $container->get('request_stack');
    $instance->currentRequest = $requestStack->getCurrentRequest();
    $instance->tempstore = $container->get('tempstore.private')->get('oauth2_client');
    return $instance;
  }

  /**
   * Route response method for validating and capturing a returned code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function code(): RedirectResponse {
    // Get data from the route.
    $clientId = $this->routeMatch->getParameter('plugin');
    $code = $this->currentRequest->query->get('code');
    // Validate.
    if (empty($code)) {
      throw new \UnexpectedValueException("The code query parameter is missing.");
    }
    $state = $this->currentRequest->query->get('state');
    if (empty($state)) {
      throw new \UnexpectedValueException("The state query parameter is missing.");
    }
    $oauth2Client = $this->entityTypeManager()->getStorage('oauth2_client')->load($clientId);
    if (!($oauth2Client instanceof Oauth2Client)) {
      throw new NotFoundHttpException();
    }
    $clientPlugin = $oauth2Client->getClient();
    $storedState = $this->tempstore->get('oauth2_client_state-' . $clientId);
    if ($state === $storedState && $clientPlugin instanceof Oauth2ClientPluginInterface) {
      // Request the Access token using the code.
      $this->grantPlugin->requestAccessToken($clientPlugin, $code);
      return $this->grantPlugin->getPostCaptureRedirect($clientPlugin);
    }
    else {
      // Potential CSRF attack. Bail out.
      $this->tempstore->delete('oauth2_client_state-' . $clientId);
      throw new NotFoundHttpException();
    }
  }

  /**
   * Route method to enable an Oauth2 Client config entity.
   *
   * @return \Drupal\Core\Routing\LocalRedirectResponse
   *   Redirect to the listing page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function enable(): LocalRedirectResponse {
    $clientId = $this->routeMatch->getParameter('oauth2_client');
    $oauth2Client = $this->entityTypeManager()->getStorage('oauth2_client')->load($clientId);
    if ($oauth2Client instanceof Oauth2Client) {
      $oauth2Client->enable();
      $oauth2Client->save();
      $this->messenger->addStatus($this->t('@client enabled', ['@client' => $oauth2Client->label()]));
    }
    $url = Url::fromRoute('entity.oauth2_client.collection')->toString(TRUE);
    return new LocalRedirectResponse($url->getGeneratedUrl());
  }

}

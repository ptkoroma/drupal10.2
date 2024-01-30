<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2Client;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * An interface for plugins that provides a redirection method.
 *
 * @package Drupal\oauth2_client\Plugin\Oauth2Client
 */
interface Oauth2ClientPluginRedirectInterface {

  /**
   * Override the default redirection with this method.
   *
   * The sole grant service that uses this redirection is authorization
   * code. If you are implementing the authorization_code grant and the default
   * in AuthorizationCodeGrantService::getPostCaptureRedirect does not meet
   * your needs,then you can implement both Oauth2ClientPluginInterface along
   * with this interface.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function getPostCaptureRedirect(): RedirectResponse;

}

<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Exception;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Redirects the user to the authentication url.
 *
 * Although generally Execeptions should not be used for flow control, we
 * need to send the user to the external authorization url while allowing
 * Drupal to shutdown and clean-up normally.
 *
 * @see https://git.drupalcode.org/project/commerce/-/blob/8.x-2.x/src/Response/NeedsRedirectException.php
 */
class AuthCodeRedirect extends EnforcedResponseException {

  /**
   * Constructs a new AuthCodeRedirect for the Authorization Code grant.
   *
   * @param string $url
   *   The URL to redirect to.
   * @param int $status_code
   *   The redirect status code.
   * @param string[] $headers
   *   Headers to pass with the redirect.
   */
  public function __construct(string $url, int $status_code = 302, array $headers = []) {
    if (!UrlHelper::isValid($url)) {
      throw new \InvalidArgumentException('Invalid URL provided.');
    }

    $response = new TrustedRedirectResponse($url, $status_code, $headers);
    $cacheable_metadata = new CacheableMetadata();
    $cacheable_metadata->setCacheMaxAge(0);
    $response->addCacheableDependency($cacheable_metadata);
    parent::__construct($response);
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\OAuth2\Client\OptionProvider;

use Drupal\oauth2_client\Plugin\Oauth2Client\Oauth2ClientPluginInterface;
use League\OAuth2\Client\OptionProvider\PostAuthOptionProvider;

/**
 * An option provider which extracts scope from the client plugin.
 */
class ClientCredentialsOptionProvider extends PostAuthOptionProvider {

  /**
   * A string of scopes imploded from the Oauth2ClientPlugin.
   */
  private string $scopeOption;

  /**
   * {@inheritdoc}
   */
  public function __construct(Oauth2ClientPluginInterface $clientPlugin) {
    $scopes = $clientPlugin->getScopes();
    if (!empty($scopes)) {
      $this->scopeOption = implode($clientPlugin->getScopeSeparator(), $scopes);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenOptions($method, array $params) {
    if (!empty($this->scopeOption)) {
      $params['scope'] = $this->scopeOption;
    }
    return parent::getAccessTokenOptions($method, $params);
  }

}

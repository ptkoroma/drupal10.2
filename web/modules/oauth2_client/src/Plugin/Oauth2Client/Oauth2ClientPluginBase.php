<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2Client;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\oauth2_client\Exception\NonrenewableTokenException;
use Drupal\oauth2_client\OwnerCredentials;
use Drupal\oauth2_client\Plugin\Oauth2GrantType\GrantWithCredentialsInterface;
use Drupal\oauth2_client\Plugin\Oauth2GrantType\Oauth2GrantTypeInterface;
use Drupal\oauth2_client\PluginManager\Oauth2GrantTypePluginManager;
use Drupal\oauth2_client\Service\CredentialProvider;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Grant\GrantFactory;
use League\OAuth2\Client\OptionProvider\OptionProviderInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\RequestFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Oauth2Client plugins.
 */
abstract class Oauth2ClientPluginBase extends PluginBase implements Oauth2ClientPluginInterface {

  /**
   * Storage for credentials retrieved from credential service.
   *
   * @var string[]
   */
  private array $credentials;

  /**
   * A set of instantiated collaborator objects.
   *
   * OPTIONAL.
   *
   * @var object[]
   */
  protected array $collaborators;

  /**
   * The grant type for this client.
   */
  protected Oauth2GrantTypeInterface $grantType;

  /**
   * The grant type for this client.
   */
  protected Oauth2GrantTypeInterface $refresh;

  /**
   * Constructs a Oauth2ClientPluginBase object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definitions.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Injected message service. Parameter declared in PluginBase.
   * @param \Drupal\oauth2_client\PluginManager\Oauth2GrantTypePluginManager $grantTypePluginManager
   *   The imjected grant plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\oauth2_client\Service\CredentialProvider $credentialService
   *   Injected credential service.
   * @param \Drupal\Core\State\StateInterface $state
   *   Injected state service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  final public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    MessengerInterface $messenger,
    Oauth2GrantTypePluginManager $grantTypePluginManager,
    protected ConfigFactoryInterface $configFactory,
    protected CredentialProvider $credentialService,
    protected StateInterface $state
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setMessenger($messenger);
    $this->grantType = $grantTypePluginManager->createInstance($plugin_definition['grant_type']);
    $this->refresh = $grantTypePluginManager->createInstance('refresh_token');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('messenger'),
      $container->get('plugin.manager.oauth2_grant_type'),
      $container->get('config.factory'),
      $container->get('oauth2_client.service.credentials'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    $name = $this->pluginDefinition['name'];
    if ($name instanceof TranslatableMarkup) {
      return $name->render();
    }
    return $name ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getClientId(): string {
    $credentials = $this->retrieveCredentials();
    return $credentials['client_id'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getClientSecret(): string {
    $credentials = $this->retrieveCredentials();
    return $credentials['client_secret'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantType(): string {
    return $this->pluginDefinition['grant_type'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUri(): string {
    $url = Url::fromRoute(
      'oauth2_client.code',
      ['plugin' => $this->getId()],
      ['absolute' => TRUE]
    );
    return $url->toString(TRUE)->getGeneratedUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUri(): string {
    return $this->pluginDefinition['authorization_uri'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenUri(): string {
    return $this->pluginDefinition['token_uri'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceUri(): string {
    return $this->pluginDefinition['resource_owner_uri'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getScopes(): array|null {
    return $this->pluginDefinition['scopes'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollaborators(): array {
    if (!empty($this->collaborators)) {
      return $this->collaborators;
    }
    $collaborators = $this->pluginDefinition['collaborators'] ?? [];
    $collaboratorObjects = [];
    foreach ($collaborators as $type => $collaborator) {
      $collaboratorObjects[$type] = new $collaborator();
    }
    // Verify:
    if (isset($collaboratorObjects['grantFactory']) && !($collaboratorObjects['grantFactory'] instanceof GrantFactory)) {
      throw new \TypeError('Collaborator key "grantFactory" must be of type GrantFactory');
    }
    if (isset($collaboratorObjects['requestFactory']) && !($collaboratorObjects['requestFactory'] instanceof RequestFactory)) {
      throw new \TypeError('Collaborator key "requestFactory" must be of type RequestFactory');
    }
    if (isset($collaboratorObjects['httpClient']) && !($collaboratorObjects['httpClient'] instanceof ClientInterface)) {
      throw new \TypeError('Collaborator key "httpClient" must be of type ClientInterface');
    }
    if (isset($collaboratorObjects['optionProvider']) && !($collaboratorObjects['optionProvider'] instanceof OptionProviderInterface)) {
      throw new \TypeError('Collaborator key "optionProvider" must be of type OptionProviderInterface');
    }

    $this->collaborators = $collaboratorObjects;
    return $this->collaborators;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopeSeparator(): string {
    return $this->pluginDefinition['scope_separator'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestOptions(array $additionalOptions = []): array {
    $options = $this->pluginDefinition['request_options'] ?? [];
    return array_merge($options, $additionalOptions);
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentialProvider(): ?string {
    $configuration = $this->getConfiguration();
    return $configuration['credentials']['credential_provider'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function displaySuccessMessage(): bool {
    return $this->pluginDefinition['success_message'] ?? FALSE;
  }

  /*
   * ##### Access Tokens #####
   */

  /**
   * Obtains an existing or a new access token.
   *
   * @param \Drupal\oauth2_client\OwnerCredentials|null $credentials
   *   Optional value object containing the username and password.
   *
   * @return \League\OAuth2\Client\Token\AccessTokenInterface|null
   *   Returns a token or null.
   *
   * @throws \Drupal\oauth2_client\Exception\NonrenewableTokenException
   *   Thrown if a token with an interactive grant type expires.
   */
  public function getAccessToken(?OwnerCredentials $credentials = NULL): ?AccessTokenInterface {
    $accessToken = $this->retrieveAccessToken();
    if ($accessToken instanceof AccessTokenInterface) {
      $expirationTimestamp = $accessToken->getExpires();
      $expired = !empty($expirationTimestamp) && $accessToken->hasExpired();
      if (!$expired) {
        return $accessToken;
      }
      $refreshToken = $accessToken->getRefreshToken();
      if (!empty($refreshToken)) {
        $accessToken = $this->refresh->getAccessToken($this);
        return $accessToken;
      }
      if ($this->getGrantType() === 'authorization_code' || $this->getGrantType() === 'resource_owner') {
        throw new NonrenewableTokenException($this->getGrantType());
      }
    }
    if ($this->grantType instanceof GrantWithCredentialsInterface) {
      $this->grantType->setUsernamePassword($credentials);
    }
    $token = $this->grantType->getAccessToken($this);
    if ($token instanceof AccessTokenInterface) {
      $this->storeAccessToken($token);
      return $token;
    }
    return NULL;
  }

  /*
   * ##### Internal Utilities #####
   */

  /**
   * Helper function to retrieve and cache credentials.
   *
   * @return string[]
   *   The credentials array.
   */
  private function retrieveCredentials(): array {
    if (empty($this->credentials)) {
      $this->credentials = $this->credentialService->getCredentials($this);
    }
    return $this->credentials;
  }

  /**
   * Creates a new provider object.
   *
   * @return \League\OAuth2\Client\Provider\GenericProvider
   *   The provider of the OAuth2 Server.
   */
  public function getProvider(): AbstractProvider {
    return new GenericProvider(
      [
        'clientId' => $this->getClientId(),
        'clientSecret' => $this->getClientSecret(),
        'redirectUri' => $this->getRedirectUri(),
        'urlAuthorize' => $this->getAuthorizationUri(),
        'urlAccessToken' => $this->getTokenUri(),
        'urlResourceOwnerDetails' => $this->getResourceUri(),
        'scopes' => $this->getScopes(),
        'scopeSeparator' => $this->getScopeSeparator(),
      ],
      $this->getCollaborators()
    );
  }

}

<?php


namespace Drupal\opigno_moxtra;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\ClientInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;


class MoxtraConnector {
  use StringTranslationTrait;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;


  /**
   * Http client.
   *
   * @var array
   */
  protected $settings;

  /**
   * Http client.
   *
   * @var string
   */
  protected $url;

  /**
   * The keyvalue storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactory
   */
  protected $keyValueStorage;

  /**
   * Constructs a MoxtraConnector object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheBackendInterface $cache,
    TimeInterface $time,
    MessengerInterface $messenger,
    ClientInterface $http_client,
    KeyValueFactory $key_value
  ) {
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('opigno_moxtra');
    $this->cache = $cache;
    $this->time = $time;
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
    $this->init();
    $this->keyValueStorage = $key_value->get('opigno_moxtra');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.factory'),
      $container->get('cache.default'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('http_client'),
      $container->get('keyvalue')
    );
  }

  /**
   * Set init options for Moxtra.
   */
  protected function init() {
    $options = $this->configFactory->get('opigno_moxtra.settings')->getRawData();
    if (isset($options['url'])) {
      $this->url = $options['url'];
    }
    unset($options['url'], $options['status']);
    $this->settings = $options;
    return $this->settings;
  }

  /**
   * Check if user is manager.
   */
  public function isManager($account) {
    $roles = $account->getRoles();
    return array_search('collaborative_features', $roles);
  }

  /**
   * Build Moxtra users prefix.
   */
  public function prefix($account) {
    $prefix = $this->keyValueStorage->get('prefix');
    $prefix = !empty($prefix) ? $prefix . '_' : '';
    return $this->isManager($account) ? 'm_' . $prefix : $prefix;
  }

  /**
   * Build Moxtra access token.
   *
   * @return string
   */
  public function getToken($uid = NULL, $force = FALSE) {
    $account = empty($uid) ? \Drupal::currentUser() : User::load($uid);
    $token = \Drupal::cache()->get('moxtra_access_token_' . $account->id());

    if (!empty($token->data) && !$force) {
      return $token->data;
    }

    $data = $this->settings;
    // For users on Moxtra not possible to change user Role or Remove user.
    // So for user with different roles we will create different Moxtra users.
    $prefix = $this->prefix($account);

    // For admin user we will use admin on Moxtra.
    if ($uid != 1) {
      unset($data['email']);
      $data['unique_id'] = $prefix . $account->id();
    }

    $url = $this->url . '/v1/core/oauth/token';
    $responce =  $this->request($url, $data);

    if (empty($responce['error']) && !empty($responce['access_token'])) {
      // Save Moxtra token for the next hour.
      \Drupal::cache()->set('moxtra_access_token_'  . $account->id(), $responce['access_token'], time() + 3600);
      if ($force) {
        \Drupal::logger('Moxtra')->notice($this->t('New token has force generated @token', ['@token' => $responce['access_token']]));
      }
      return $responce['access_token'];
    }
    else {
     \Drupal::cache()->set('moxtra_access_token', NULL, 0);
      return '';
    }
  }

  /**
   * Get Moxtra APP url.
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Get Moxtra APP admin email.
   */
  public function getEmail() {
    if (isset($this->settings['email'])) {
      return $this->settings['email'];
    }
    return '';
  }

  /**
   * Get Moxtra org_id.
   */
  public function getOrgId() {
    return $this->settings['org_id'];
  }

  /**
   * Check if Moxtra settings is fine.
   */
  public function checkSettings() {
    $fields = [
      'client_id',
      'client_secret',
      'email',
      'org_id'
    ];
    foreach ($fields as $field) {
      if (empty($this->settings[$field])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Helper function to send a request with JSON data to the Moxtra API.
   *
   * @param string $url
   *   Request URL.
   * @param array $request_data
   *   Request data.
   * @param string $method
   *   HTTP method.
   *
   * @return array
   *   Response data.
   */
  public function request($url, array $request_data, $method = 'POST') {
    $data = [];
    if (!$this->checkSettings()) {
      return [];
    }

    try {
      $response = $this->httpClient->request($method, $url, [
        'json' => $request_data,
      ]);
    }
    catch (RequestException $exception) {
      $this->logger->error($exception);
      $response = $exception->getResponse();
      $data['error'] = $exception->getMessage();
    }
    catch (\Exception $exception) {
      $this->logger->error($exception);
      $data['error'] = $exception->getMessage();
    }

    if (isset($response)) {
      $data['http_code'] = $response->getStatusCode();
      $response_body = $response->getBody()->getContents();
      if (!empty($response_body) && $response_body !== 'null') {
        $json_data = Json::decode($response_body);
        if (is_array($json_data) && !empty($json_data)) {
          $data = array_merge($data, $json_data);
        }
      }

      if ($data['http_code'] == 400) {
        if (isset($data['message'])
          && $data['message'] == 'cann\'t expel owner') {
          // Ignore 'cann't expel owner' error.
          $data['http_code'] = 200;
        }
      }

      if ($data['http_code'] == 404) {
        // Ignore 'User not found in member list.' error.
        $data['http_code'] = 200;
      }

      if ($data['http_code'] == 409) {
        // Ignore 'all invitees are already members' error.
        $data['http_code'] = 200;
      }

      if ($data['http_code'] != 200) {
        $this->logger->error($this->t('Error while contacting the Moxtra server.<br/><pre>Response: @response</pre>', [
          '@response' => print_r($data, TRUE),
        ]));
      }
    }
    else {
      $this->messenger->addError($this->t('Error while contacting the Moxtra server. Try again or contact the administrator.'));
      $data['http_code'] = 501;
    }

    return $data;
  }

}

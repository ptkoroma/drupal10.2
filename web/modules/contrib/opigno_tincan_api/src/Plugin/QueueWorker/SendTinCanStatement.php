<?php

namespace Drupal\opigno_tincan_api\Plugin\QueueWorker;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use TinCan\RemoteLRS;

/**
 * Send TinCan Statement.
 *
 * @QueueWorker(
 *   id = "opigno_tincan_send_tincan_statement",
 *   title = @Translation("Send TinCan Statement via queue"),
 *   cron = {"time" = 20}
 * )
 */
class SendTinCanStatement extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Config factory service.
   */
  protected $configFactory;


  /**
   * Logger system service.
   */
  protected $logger;


  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $config_factory, $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // _opigno_tincan_api_send_statement.
    // The variables 'opigno_tincan_api_*'
    // will be used to send the statement to the LRS.
    $config = $this->configFactory->get('opigno_tincan_api.settings');
    $endpoint = $config->get('opigno_tincan_api_endpoint');
    $username = $config->get('opigno_tincan_api_username');
    $password = $config->get('opigno_tincan_api_password');

    if (empty($endpoint) || empty($username) || empty($password)) {
      return FALSE;
    }

    $lrs = new RemoteLRS(
      $endpoint,
      '1.0.1',
      $username,
      $password
    );
    $response = $lrs->saveStatement($data->statement);

    if ($response->success === FALSE) {
      $this->logger->get('Opigno Tincan API')
        ->error('The following statement could not be sent :<br /><pre>' . print_r($statement->asVersion('1.0.1'), TRUE) . '</pre><br/>', []);

      return FALSE;
    }

    return TRUE;
  }
}

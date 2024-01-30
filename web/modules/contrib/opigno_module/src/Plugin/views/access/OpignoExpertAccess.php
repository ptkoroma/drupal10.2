<?php

namespace Drupal\opigno_module\Plugin\views\access;

use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Psr\Container\ContainerInterface;

/**
 * Checks experts access for views.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "opigno_expert_access",
 *   title = @Translation("Opigno Expert Access"),
 *   help = @Translation("Checks expert access.")
 * )
 */
class OpignoExpertAccess extends AccessPluginBase {

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The access checker service.
   *
   * @var \Drupal\opigno_module\Access\ExpertAccessCheck
   */
  protected $accessChecker;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

    // Instantiates this form class.
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    $instance->accessChecker = $container->get('opigno_module.expert_access_checker');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Opigno Expert Access');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $this->accessChecker->checkAccess($account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_opigno_expert_access_check', 'TRUE');
  }

}

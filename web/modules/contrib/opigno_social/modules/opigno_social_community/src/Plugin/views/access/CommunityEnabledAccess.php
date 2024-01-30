<?php

namespace Drupal\opigno_social_community\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Access\CommunityEnabledAccessCheck;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Allows the access to the view only if community features enabled.
 *
 * @ViewsAccess(
 *   id = "opigno_community_enabled_access",
 *   title = @Translation("Opigno communities enabled"),
 *   help = @Translation("Allows the access to the view only if community features enabled")
 * )
 *
 * @ingroup views_access_plugins
 *
 * @package Drupal\opigno_social_community\Plugin\views\access
 */
class CommunityEnabledAccess extends AccessPluginBase {

  /**
   * The community member access check service.
   *
   * @var \Drupal\opigno_social_community\Access\CommunityEnabledAccessCheck
   */
  protected CommunityEnabledAccessCheck $accessCheck;

  /**
   * {@inheritdoc}
   */
  public function __construct(CommunityEnabledAccessCheck $access_check, ...$default) {
    parent::__construct(...$default);
    $this->accessCheck = $access_check;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_social_community.enabled_access_check'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Opigno community enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $access = $this->accessCheck->access();
    return $access->isAllowed() && $account->isAuthenticated();
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_opigno_social_community_enabled', 'TRUE');
    $route->setRequirement('_role', 'authenticated');
  }

}

<?php

namespace Drupal\opigno_social_community\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_social_community\Access\CommunityOwnerAccessCheck;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Allows the access to the view only to the community owner.
 *
 * @ViewsAccess(
 *   id = "opigno_community_owner_access",
 *   title = @Translation("Opigno community owner"),
 *   help = @Translation("Allows the access to the view only to the community owner")
 * )
 *
 * @ingroup views_access_plugins
 *
 * @package Drupal\opigno_social_community\Plugin\views\access
 */
class CommunityOwnerAccess extends AccessPluginBase {

  /**
   * The community member access check service.
   *
   * @var \Drupal\opigno_social_community\Access\CommunityOwnerAccessCheck
   */
  protected CommunityOwnerAccessCheck $accessCheck;

  /**
   * {@inheritdoc}
   */
  public function __construct(CommunityOwnerAccessCheck $access_check, ...$default) {
    parent::__construct(...$default);
    $this->accessCheck = $access_check;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('opigno_social_community.owner_access_check'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Opigno community owner');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    return $this->accessCheck->checkAccess($account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_opigno_community_owner', 'TRUE');
  }

}

<?php

namespace Drupal\opigno_social\Access;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\opigno_module\Entity\OpignoModuleInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Check if the user can share the given content.
 *
 * @package Drupal\opigno_social\Access
 */
class ShareContentAccessCheck implements AccessInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ShareContentAccessCheck constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->request = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks the access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check the access to.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in user's account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account) {
    $uid = (int) $account->id();
    // Users with the specific permission can share any content.
    if ($account->hasPermission('share any content')) {
      return AccessResult::allowed()->addCacheTags(['user:' . $uid]);
    }

    if (!$this->request instanceof Request) {
      return AccessResult::forbidden();
    }

    // Load the entity from the request.
    $entity_type = $this->request->get('entity_type', '');
    $eid = $this->request->get('id', '');

    if (!$entity_type || !$eid) {
      return AccessResult::forbidden();
    }

    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($eid);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_social_exception', $e);
      $entity = NULL;
    }

    // Check the access depending on the sharable entity type.
    if ($entity instanceof GroupInterface && $entity->getMember($account)) {
      $access = AccessResult::allowed();
    }
    elseif ($entity instanceof OpignoModuleInterface) {
      $url = Url::fromRoute('opigno_module.my_results', ['opigno_module' => 28]);
      $access = AccessResult::allowedIf($url->access());
    }
    else {
      $access = AccessResult::forbidden();
    }

    return $access;
  }

}

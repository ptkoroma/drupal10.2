<?php

namespace Drupal\opigno_certificate\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Session\AccountInterface;
use Drupal\opigno_certificate\OpignoCertificateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a controller to render a single opigno_certificate.
 */
class CertificateController extends EntityViewController {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $current_user, ...$default) {
    parent::__construct(...$default);
    $this->currentUser = $current_user ?: \Drupal::currentUser();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * Callback to view the opigno_certificate entity attached to any entity.
   */
  public function viewEntity($entity_type, $entity_id, $view_mode = 'full') {
    try {
      $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException $e) {
      watchdog_exception('opigno_certificate_exception', $e);
      $entity = NULL;
    }

    if (!$entity instanceof ContentEntityInterface || !$entity->hasField('field_certificate')) {
      return [];
    }

    $opigno_certificate = $entity->get('field_certificate')->entity;
    if (!$opigno_certificate instanceof OpignoCertificateInterface) {
      return [];
    }

    // We're going to render the opigno_certificate,
    // but the opigno_certificate will need pull
    // information from the entity that references it. So set the
    // 'referencing_entity' computed field to the entity being displayed.
    $opigno_certificate->set('referencing_entity', $entity);

    return $this->view($opigno_certificate, $view_mode);
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $opigno_certificate, $view_mode = 'full') {
    /** @var \Drupal\opigno_certificate\OpignoCertificateInterface $opigno_certificate */

    // @todo: check opigno_certificate access before rendering the opigno_certificate.
    // @todo: implement entity access to check that user has completed learning
    // path. This will need to be a custom access operation other than 'view'.
    /*if ($entity = $opigno_certificate->referencing_entity->entity) {
    $title = $entity->label();
    }*/

    if (in_array($view_mode, ['full', 'default']) && $opigno_certificate->getViewModeSelectorField()) {
      $view_mode = 'view_mode_selector';
    }

    $build = parent::view($opigno_certificate, $view_mode);

    return $build;
  }

  /**
   * Checks access for the controller.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function viewAccess($entity_type, $entity_id): AccessResultInterface {
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity instanceof ContentEntityInterface || !$entity->hasField('field_certificate')) {
      return AccessResult::forbidden();
    }

    $opigno_certificate = $entity->get('field_certificate')->entity;
    if (!$opigno_certificate instanceof OpignoCertificateInterface) {
      return AccessResult::forbidden();
    }

    $opigno_certificate->set('referencing_entity', $entity);
    $access_result = AccessResult::allowedIfHasPermission($this->currentUser, 'administer certificates');
    if ($access_result->isAllowed()) {

      return $access_result;
    }

    // Check access against the entity referencing the opigno_certificate
    // instead of the opigno_certificate itself,
    // so that each entity can have its own access check,
    // but use 'view opigno_certificate'
    // so that the access is specific to viewing opigno_certificates.
    $access_result = $entity->access('view certificate', $this->currentUser, TRUE);

    return $access_result instanceof AccessResultInterface ? $access_result : AccessResult::forbidden();
  }

}

<?php

namespace Drupal\opigno_module;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Activity entities.
 *
 * @see Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 * @see Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class OpignoActivityHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $entity_type_id = $entity_type->id();

    if ($collection_route = $this->getCollectionRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.collection", $collection_route);
    }

    if ($settings_form_route = $this->getSettingsFormRoute($entity_type)) {
      $collection->add("$entity_type_id.settings", $settings_form_route);
    }

    if ($history_route = $this->getHistoryRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.version_history", $history_route);
    }

    if ($revert_route = $this->getRevisionRevertRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_revert", $revert_route);
    }

    if ($delete_route = $this->getRevisionDeleteRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision_delete", $delete_route);
    }

    if ($revision_view_route = $this->getRevisionViewRoute($entity_type)) {
      $collection->add("entity.{$entity_type_id}.revision-preview", $revision_view_route);
    }

    return $collection;
  }

  /**
   * Gets the revision delete route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionDeleteRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision-delete')) {
      $route = new Route($entity_type->getLinkTemplate('revision-delete'));
      $route
        ->setDefaults([
          '_form' => '\Drupal\opigno_module\Form\OpignoActivityRevisionDeleteForm',
          '_title' => 'Delete earlier revision',
        ])
        ->setOption('parameters', [
          'opigno_activity' => ['type' => 'entity:opigno_activity'],
          'opigno_activity_revision' => ['type' => 'entity_revision:opigno_activity'],
        ])
        ->setRequirement('_permission', 'delete all activity revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the revision revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision-revert')) {
      $route = new Route($entity_type->getLinkTemplate('revision-revert'));
      $route
        ->setDefaults([
          '_form' => '\Drupal\opigno_module\Form\OpignoActivityRevisionRevertForm',
          '_title' => 'Revert to earlier revision',
        ])
        ->setOption('parameters', [
          'opigno_activity' => ['type' => 'entity:opigno_activity'],
          'opigno_activity_revision' => ['type' => 'entity_revision:opigno_activity'],
        ])
        ->setRequirement('_permission', 'revert all activity revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the version history route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getHistoryRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('version-history')) {
      $route = new Route($entity_type->getLinkTemplate('version-history'));
      $route
        ->setDefaults([
          '_title' => "{$entity_type->getLabel()} revisions",
          '_controller' => '\Drupal\opigno_module\Controller\ActivityRevisionsController::revisionOverview',
        ])
        ->setOption('parameters', ['opigno_activity' => ['type' => 'entity:opigno_activity']])
        ->setRequirement('_permission', 'access activity revisions')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the revision preview route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRevisionViewRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('revision-preview')) {
      $route = new Route($entity_type->getLinkTemplate('revision-preview'));
      $route
        ->setDefaults([
          '_title' => "{$entity_type->getLabel()} revisions",
          '_controller' => '\Drupal\opigno_module\Controller\ActivityRevisionsController::previewActivityRevision',
        ])
        ->setOption('parameters', [
          'opigno_activity' => ['type' => 'entity:opigno_activity'],
          'opigno_activity_revision',
        ])
        ->setRequirement('_permission', 'access activity revisions')
        ->setRequirement('opigno_activity_revision', '\d+')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the collection route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getCollectionRoute(EntityTypeInterface $entity_type) {
    if ($entity_type->hasLinkTemplate('collection') && $entity_type->hasListBuilderClass()) {
      $entity_type_id = $entity_type->id();
      $route = new Route($entity_type->getLinkTemplate('collection'));
      $route
        ->setDefaults([
          '_entity_list' => $entity_type_id,
          '_title' => "{$entity_type->getLabel()} list",
        ])
        ->setRequirement('_permission', 'access activity overview')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

  /**
   * Gets the settings form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getSettingsFormRoute(EntityTypeInterface $entity_type) {
    if (!$entity_type->getBundleEntityType()) {
      $route = new Route("/admin/structure/{$entity_type->id()}/settings");
      $route
        ->setDefaults([
          '_form' => 'Drupal\opigno_module\Form\OpignoActivitySettingsForm',
          '_title' => "{$entity_type->getLabel()} settings",
        ])
        ->setRequirement('_permission', $entity_type->getAdminPermission())
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

}

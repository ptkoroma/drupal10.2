<?php

namespace Drupal\opigno_module\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('entity.opigno_module.collection')) {
      $route->setPath('/admin/structure/opigno-modules');
    }

    if ($route = $collection->get('entity.opigno_activity.collection')) {
      $route->setPath('/admin/structure/opigno-activities');
    }

    if ($route = $collection->get('entity.group.collection')) {
      $route->setPath('/admin/structure/groups');
    }

    // Rewrite access to Drupal\entity_browser\Entity\EntityBrowser::route()
    // to allow group content manager add an image to a module.
    if ($route = $collection->get('entity_browser.media_entity_browser_groups')) {
      $route->setRequirements(['_custom_access' => '\Drupal\opigno_module\Controller\OpignoModuleController::accessEntityBrowserGroups']);
    }

    // H5P libraries list.
    if ($route = $collection->get('h5peditor.content_type_cache')) {
      $route->setDefaults([
        '_controller' => '\Drupal\opigno_module\Controller\OpignoH5PEditorAJAXController::contentTypeCacheCallback',
      ]);
    }
  }

}

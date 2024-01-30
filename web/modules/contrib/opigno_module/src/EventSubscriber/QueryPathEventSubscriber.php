<?php

namespace Drupal\opigno_module\EventSubscriber;

use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class QueryPathEventSubscriber implements EventSubscriberInterface {

  // Remember query string (with table sort) as session variable and reload page with it
  public function checkRedirection(ResponseEvent $event) {
    // Add new routes for enable logic
    $available_routes = [
      'entity.opigno_activity.collection',
      'entity.opigno_module.collection',
      'entity.group.collection'
    ];
    $route = \Drupal::routeMatch();
    $session = \Drupal::request()->getSession();

    if (in_array($route->getRouteName(),$available_routes)) {
      $param = \Drupal::request()->query->all();

      // If we have an empty page (without new query params), we load previous one
      if (empty($param)) {
        $order_values = $session->get($route->getRouteName());
        if (!empty($order_values)) {
          $url = Url::fromRoute($route->getRouteName());
          $url->setOption('query', $order_values);
          $response = new RedirectResponse($url->toString());
          $event->setResponse($response);
        }
      }
      else {
        $session->set($route->getRouteName(), $param);
      }
    }
  }

  static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['checkRedirection'];
    return $events;
  }
}





//  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
//    // Get the carry query configuration settings.
//    $config = $this->configFactory->get('carryquery.settings');
//    $keys = $config->get('keys');
//    $inkeys = $request->query->keys();
//    // Get the common keys from both keys stored in the configuration and
//    // kyes of query address parameters.
//    if (is_array($keys) && is_array($inkeys)) {
//      $commonkeys = array_intersect($keys, $inkeys);
//    }
//    // Add the cache context for query arguments.
//    if ($bubbleable_metadata) {
//      $bubbleable_metadata->addCacheContexts(['url.query_args']);
//    }
//
//    if (isset($commonkeys)) {
//      foreach ($commonkeys as $value) {
//        $options['query'][$value] = $request->query->get($value);
//      }
//    }
//    if ($path == '/admin/structure/opigno-modules') {
//      $options['query']['order'] = 'name';
//      $options['query']['sort'] = 'asc';
//      ksm($request->query->keys());
//      return '/admin/structure/opigno-modules?name=&order=name&sort=asc';
//    }
//    return $path;
//  }
//
//}

<?php

namespace Drupal\opigno_messaging\Plugin\views\sort;

use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\views\Plugin\views\sort\SortPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Custom threads sorting handler: display the current one first, then others.
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort ("current_thread_first")
 */
class OpignoMessageThreadsSorting extends SortPluginBase {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The router service.
   *
   * @var \Drupal\Core\Routing\AccessAwareRouterInterface
   */
  protected $router;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteMatchInterface $route_match, AccessAwareRouterInterface $router, RequestStack $request_stack, ...$default) {
    parent::__construct(...$default);
    $this->routeMatch = $route_match;
    $this->router = $router;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('current_route_match'),
      $container->get('router'),
      $container->get('request_stack'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $order = $this->options['order'];

    // If the user is on the single thread page, the current thread should
    // always be displayed at the top.
    // The pager is working with ajax, so need to get the thread from the
    // referer.
    if ($this->request instanceof Request && $this->request->isXmlHttpRequest()) {
      $route = $this->request->server->get('HTTP_REFERER');
      $route_info = $this->router->match($route);
      $params = $route_info['_raw_variables'] ?? NULL;
      $thread = $params instanceof ParameterBag ? $params->get('private_message_thread') : NULL;
    }
    else {
      $thread = $this->routeMatch->getRawParameter('private_message_thread');
    }

    if ($thread) {
      $this->query->addOrderBy(NULL, "FIELD($this->tableAlias.id, $thread)", $order, 'current_thread');
    }

    // Default sort by the last update.
    $this->query->addOrderBy(NULL, "$this->tableAlias.$this->realField", $order, 'by_date');
  }

}

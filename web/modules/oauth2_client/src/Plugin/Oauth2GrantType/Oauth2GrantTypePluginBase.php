<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Plugin\Oauth2GrantType;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for oauth2_grant_type plugins.
 */
abstract class Oauth2GrantTypePluginBase extends PluginBase implements Oauth2GrantTypeInterface {

  /**
   * The Request Stack.
   */
  protected Request $currentRequest;

  /**
   * {@inheritdoc}
   */
  final public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    $requestStack,
    protected StateInterface $state,
    protected UrlGeneratorInterface $urlGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $currentRequest = $requestStack->getCurrentRequest();
    if (!($currentRequest instanceof Request)) {
      // The method can return null.
      throw new \UnexpectedValueException('RequestStack::getCurrentRequest did not return a request.');
    }
    $this->currentRequest = $currentRequest;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request */
    $request = $container->get('request_stack');
    /** @var \Drupal\Core\State\StateInterface $state */
    $state = $container->get('state');
    /** @var \Drupal\Core\Routing\UrlGeneratorInterface $url */
    $url = $container->get('url_generator');
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $request,
      $state,
      $url,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

}

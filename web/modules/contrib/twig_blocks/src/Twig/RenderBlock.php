<?php

namespace Drupal\twig_blocks\Twig;

use Drupal\block\Entity\Block;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds twig extension to render a block.
 */
class RenderBlock extends AbstractExtension {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor method.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RendererInterface $renderer, EntityTypeManagerInterface $entity_type_manager) {
    $this->renderer = $renderer;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'render_block';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('render_block', [$this, 'renderBlock'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Provides function to programmatically rendering a block.
   *
   * @param string $block_id
   *   The machine id of the block to render.
   * @param array $configuration
   *   The configuration for the block.
   *
   * @return array
   *   Returns render array of block.
   *
   * @throws \Exception
   */
  public function renderBlock($block_id, $configuration = []) {
    $block = Block::load($block_id);
    $markup = [];
    if ($block) {
      if (!empty($configuration)) {
        $block_settings = $block->get('settings');
        $block_settings = array_merge($block_settings, $configuration);
        $block->set('settings', $block_settings);
        $block->save();
      }
      $markup = $this->entityTypeManager->getViewBuilder('block')->view($block);
    }
    return ['#markup' => $this->renderer->render($markup)];
  }

}

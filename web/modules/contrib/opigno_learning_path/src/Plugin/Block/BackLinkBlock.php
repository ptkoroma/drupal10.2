<?php

namespace Drupal\opigno_learning_path\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\system\Plugin\Block\SystemBreadcrumbBlock;

/**
 * Provides a backlinkblock block.
 *
 * @Block(
 *   id = "opigno_learning_path_back_link_block",
 *   admin_label = @Translation("BackLinkBlock"),
 *   category = @Translation("Custom")
 * )
 */
class BackLinkBlock extends SystemBreadcrumbBlock implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]|void
   */
  protected $isOverrideJS = NULL;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'js_button' => implode(PHP_EOL, [
        'user.login',
        'user.register',
        'user.pass',
        'opigno_module.group.answer_form',
        'entity.group.canonical',
        'view.opigno_training_catalog.training_catalogue',
        'opigno_module.module_result',
      ]),
    ];
  }

  /**
   * Builds the breadcrumb links.
   */
  public function getLinks() {
    return $this->breadcrumbManager->build($this->routeMatch)->getLinks() ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;
    $form['js_button'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Breadcrumbs routes'),
      '#default_value' => $config['js_button'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path']);
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $js_button = $form_state->getValue('js_button');
    $this->configuration['js_button'] = $js_button;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\Core\Link[] $links */
    $links = $this->getLinks();
    $last_url = '';
    $last_link = NULL;

    // Get the last clickable link.
    for ($i = count($links); $i >= 0; $i--) {
      $last_link = array_pop($links);
      $last_url = $last_link->getUrl()->toString();
      if ($last_url) {
        break;
      }
    }

    if ($last_link instanceof Link) {
      return [
        '#type' => 'inline_template',
        '#template' => '<div class="back-btn d-none d-lg-block {{js_button}}"><a href="{{context}}"><i class="fi fi-rr-angle-small-left d-lg-none"></i><i class="fi fi-rr-arrow-left d-none d-lg-block"></i>{{context_title}}</a></div>',
        '#context' => [
          'context_title' => $this->t('Back'),
          'context' => $last_url,
        ],
        '#attached' => [
          'library' => ['opigno_learning_path/back_button'],
          'drupalSettings' => [
            'learning_path_back_link' => ['js_button' => $this->isOverrideJS(),],
          ],
        ],
      ];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function isOverrideJS() {
    if (is_null($this->isOverrideJS)) {
      $this->setOverrideJS();
    }
    return $this->isOverrideJS;
  }

  /**
   * {@inheritdoc}
   */
  protected function setOverrideJS() {
    // Add the route name as an extra class to body.
    $route = (string) \Drupal::routeMatch()->getRouteName();
    return $this->isOverrideJS = !in_array($route, preg_split('/\n|\r\n?/', $this->configuration['js_button']));
  }

}

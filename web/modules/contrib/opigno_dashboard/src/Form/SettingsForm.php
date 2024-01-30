<?php

namespace Drupal\opigno_dashboard\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\opigno_dashboard\BlockServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The block service.
   *
   * @var \Drupal\opigno_dashboard\BlockServiceInterface
   */
  protected $blockService;

  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Constructs a \Drupal\opigno_dashboard\Form\SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\opigno_dashboard\BlockServiceInterface $block_service
   *   The block service object.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler.
   */
  public function __construct(ConfigFactoryInterface $config_factory, BlockServiceInterface $block_service, ThemeHandlerInterface $theme_handler) {
    parent::__construct($config_factory);
    $this->blockService = $block_service;
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('opigno_dashboard.block'),
      $container->get('theme_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'opigno_dashboard_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['opigno_dashboard.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#title'] = $this->t('Dashboard settings');

    $config = $this->config('opigno_dashboard.settings');
    $config_blocks = $config->get('blocks');
    $config_theme = $config->get('theme');
    $blocks = $this->blockService->getAllBlocks();

    $themes = [];
    foreach ($this->themeHandler->listInfo() as $theme) {
      $themes[$theme->getName()] = $theme->info['name'];
    }
    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme selection'),
      '#options' => $themes,
      '#default_value' => isset($config_theme) ? $config_theme : $this->config('system.theme')->get('default'),
      '#description' => $this->t("The dashboard blocks will be created under this theme. A region named 'content' is required."),
    ];

    $form['blocks_label'] = [
      '#type' => 'item',
      '#title' => $this->t('Dashboard Blocks'),
    ];

    $form['blocks'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Available'),
        $this->t('Mandatory'),
      ],
    ];

    foreach ($blocks as $id => $block) {
      $form['blocks'][$id]['name'] = [
        '#markup' => $block['admin_label'],
      ];

      $form['blocks'][$id]['available'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Available'),
        '#title_display' => 'invisible',
        '#default_value' => isset($config_blocks[$id]['available']) ? $config_blocks[$id]['available'] : NULL,
      ];

      $form['blocks'][$id]['mandatory'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Mandatory'),
        '#title_display' => 'invisible',
        '#default_value' => isset($config_blocks[$id]['mandatory']) ? $config_blocks[$id]['mandatory'] : NULL,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save settings.
    $this->config('opigno_dashboard.settings')
      ->set('blocks', $form_state->getValue('blocks'))
      ->set('theme', $form_state->getValue('theme'))
      ->save();

    $this->blockService->createBlocksInstances($form_state->getValue('blocks'));

    parent::submitForm($form, $form_state);
  }

}

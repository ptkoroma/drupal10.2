<?php

namespace Drupal\opigno_module\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\h5peditor\H5PEditor\H5PEditorUtilities;
use Drupal\opigno_module\H5PImportClasses\H5PEditorAjaxImport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the H5P editor ajax controller.
 *
 * @package Drupal\opigno_module\Controller
 */
class OpignoH5PEditorAJAXController extends ControllerBase {

  /**
   * OpignoH5PEditorAJAXController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Callback that returns the content type cache.
   */
  public function contentTypeCacheCallback(): void {
    $editor = H5PEditorUtilities::getInstance();

    $h5pEditorAjax = new H5PEditorAjaxImport($editor->ajax->core, $editor, $editor->ajax->storage);
    $libraries = $h5pEditorAjax->h5pLibariesList();

    $this->filterLibraries($libraries);

    \H5PCore::ajaxSuccess($libraries, TRUE);
    exit();
  }

  /**
   * Excludes disabled libraries.
   *
   * @param array $libraries
   *   The list of H5P libraries to be filtered.
   */
  public function filterLibraries(array &$libraries): void {
    // Get disabled list.
    $config = $this->config('opigno_module.settings');
    $disabled = $config->get('disabled_h5p') ?: [];

    foreach ($libraries['libraries'] as $key => $library) {
      if (in_array($library['machineName'], $disabled)) {
        unset($libraries['libraries'][$key]);
      }
    }

    $libraries['libraries'] = array_values($libraries['libraries']);
  }

}

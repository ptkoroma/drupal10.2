<?php

namespace Drupal\opigno_catalog\TwigExtension;

use Drupal\Core\Render\Markup;
use Drupal\group\Entity\Group;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Class DefaultTwigExtension.
 */
class DefaultTwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction(
        'opigno_catalog_get_style',
        [$this, 'get_row_style']
      ),
      new TwigFunction(
        'opigno_catalog_is_member',
        [$this, 'is_member']
      ),
      new TwigFunction(
        'opigno_catalog_is_started',
        [$this, 'is_started']
      ),
      new TwigFunction(
        'opigno_catalog_get_default_image',
        [$this, 'get_default_image']
      ),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'opigno_catalog.twig.extension';
  }

  /**
   * Gets row style.
   */
  public function get_row_style() {
    $style = \Drupal::service('opigno_catalog.get_style')->getStyle();

    return ($style == 'line') ? 'style-line' : 'style-block';
  }

  /**
   * Checks if user is a member of group.
   */
  public function is_member($group_id) {
    $group = Group::load($group_id);
    $account = \Drupal::currentUser();

    return (bool) $group->getMember($account);
  }

  /**
   * Checks if training started.
   */
  public function is_started($group_id) {
    $group = Group::load($group_id);
    $account = \Drupal::currentUser();

    return (bool) opigno_learning_path_started($group, $account);
  }

  /**
   * Returns default image.
   */
  public function get_default_image($type, $title) {
    $request = \Drupal::request();
    $path = \Drupal::service('module_handler')
      ->getModule('opigno_catalog')
      ->getPath();
    $title = t('Picture of') . ' ' . $title;
    switch ($type) {
      case 'course':
        $img = '<img src="' . $request->getBasePath() . '/' . $path . '/img/img_course.png" alt="' . $title . '">';
        break;

      case 'module':
        $img = '<img src="' . $request->getBasePath() . '/' . $path . '/img/img_module.png" alt="' . $title . '">';
        break;

      case 'learning_path':
        $theme_path = \Drupal::service('extension.list.theme')->getPath('aristotle');
        $img = '<img src="' . $request->getBasePath() . '/' . $theme_path . '/src/images/content/training.svg" alt="' . $title . '">';
        break;

      // @TODO Move to the related module.
      case 'certificate_image':
        $theme_path = \Drupal::service('extension.list.theme')->getPath('aristotle');
        $img = '<img src="' . $request->getBasePath() . '/' . $theme_path . '/src/images/design/certificate.svg" alt="' . $title . '">';
        break;

      default:
        $img = NULL;
        break;
    }

    return Markup::create($img);
  }

}

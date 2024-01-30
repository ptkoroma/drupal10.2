<?php

namespace Drupal\opigno_social\Plugin\views\field;

use Drupal\Core\Database\Connection;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * View field handler to implement Opigno badge name.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("opigno_badge_name")
 */
class BadgeName extends FieldPluginBase {

  /**
   * The DB connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * {@inheritdoc}
   */
  public function __construct(Connection $database, ...$default) {
    parent::__construct(...$default);
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('database'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Query isn't needed.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $typology = $values->opigno_module_badges_typology ?? '';
    $id = $values->opigno_module_badges_entity_id ?? '';
    if (!$id) {
      return '';
    }

    // Get the name of the badge depending on the entity type.
    switch ($typology) {
      case 'Course':
        $result = $this->database->select('group__badge_name', 'gbn')
          ->fields('gbn', ['badge_name_value'])
          ->condition('gbn.entity_id', $id)
          ->condition('gbn.bundle', 'opigno_course')
          ->execute()
          ->fetchField();
        break;

      case 'Module':
        $result = $this->database->select('opigno_module_field_data', 'om')
          ->fields('om', ['badge_name'])
          ->condition('om.id', $id)
          ->execute()
          ->fetchField();
        break;

      default:
        $result = '';
    }

    return (string) $result;
  }

}

<?php

namespace Drupal\opigno_social\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter view handler for the Opigno badge name.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_badge_name")
 */
class BadgeNameFilter extends StringFilter {

  /**
   * The join manager service.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ViewsHandlerManager $join_manager, ...$default) {
    parent::__construct(...$default);
    $this->joinManager = $join_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('plugin.manager.views.join'),
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (!$this->query instanceof Sql) {
      return;
    }

    // Prepare the query. Badges info is stored in different tables for groups
    // (courses) and modules (custom entity). We'll join both tables and apply
    // the filter for all results.
    $this->ensureMyTable();
    // Join the table to get course badge names.
    $course_badges_join_definition = [
      'left_table' => $this->tableAlias,
      'left_field' => $this->realField,
      'table' => 'group__badge_name',
      'field' => 'entity_id',
      'adjusted' => TRUE,
      'type' => 'LEFT',
      'extra' => [
        [
          'left_field' => 'typology',
          'value' => 'Course',
        ],
        [
          'field' => 'bundle',
          'value' => 'opigno_course',
        ],
      ],
    ];
    $course_badges_join = $this->joinManager->createInstance('standard', $course_badges_join_definition);
    $course_badges_alias = $this->query->addTable('group__badge_name', NULL, $course_badges_join);

    // Join the table to get module badge names.
    $module_badges_join_definition = [
      'left_table' => $this->tableAlias,
      'left_field' => $this->realField,
      'table' => 'opigno_module_field_data',
      'field' => 'id',
      'adjusted' => TRUE,
      'type' => 'LEFT',
      'extra' => [
        [
          'left_field' => 'typology',
          'value' => 'Module',
        ],
      ],
    ];
    $module_badges_join = $this->joinManager->createInstance('standard', $module_badges_join_definition);
    $module_badges_alias = $this->query->addTable('opigno_module_field_data', NULL, $module_badges_join);

    // Check if the given text is a part of any badge name that was earned in
    // the course or in the module.
    $condition = new Condition('OR');
    $val = $this->connection->escapeLike($this->value);
    $condition->condition("$course_badges_alias.badge_name_value", "%$val%", 'LIKE');
    $condition->condition("$module_badges_alias.badge_name", "%$val%", 'LIKE');
    $this->query->addWhere($this->options['group'], $condition);
  }

}

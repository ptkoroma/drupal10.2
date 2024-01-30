<?php

namespace Drupal\opigno_messaging\Plugin\views\filter;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\filter\StringFilter;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\views\Plugin\ViewsHandlerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter view handler for the Opigno message thread name.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("opigno_pm_thread_name")
 */
class OpignoMessageThreadNameFilter extends StringFilter {

  /**
   * The join manager service.
   *
   * @var \Drupal\views\Plugin\ViewsHandlerManager
   */
  protected $joinManager;

  /**
   * The current user ID.
   *
   * @var int
   */
  protected $currentUid;

  /**
   * {@inheritdoc}
   */
  public function __construct(ViewsHandlerManager $join_manager, AccountInterface $account, ...$default) {
    parent::__construct(...$default);
    $this->joinManager = $join_manager;
    $this->currentUid = (int) $account->id();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('plugin.manager.views.join'),
      $container->get('current_user'),
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

    // Prepare the query.
    $this->ensureMyTable();
    // Join the table to get all thread members, exclude the current one.
    $members_join_definition = [
      'left_table' => $this->tableAlias,
      'left_field' => $this->realField,
      'table' => 'private_message_thread__members',
      'field' => 'entity_id',
      'extra' => [
        [
          'field' => 'members_target_id',
          'value' => $this->currentUid,
          'operator' => '!=',
        ],
      ],
    ];
    $members_join = $this->joinManager->createInstance('standard', $members_join_definition);
    $members_alias = $this->query->addTable('private_message_thread__members', NULL, $members_join);

    // Join the table to get the user first name.
    $first_name_join_definition = [
      'left_table' => $members_alias,
      'left_field' => 'members_target_id',
      'table' => 'user__field_first_name',
      'field' => 'entity_id',
    ];
    $first_name_join = $this->joinManager->createInstance('standard', $first_name_join_definition);
    $first_name_alias = $this->query->addTable('user__field_first_name', NULL, $first_name_join);

    // Join the table to get the user last name.
    $last_name_join_definition = [
      'left_table' => $members_alias,
      'left_field' => 'members_target_id',
      'table' => 'user__field_last_name',
      'field' => 'entity_id',
    ];
    $last_name_join = $this->joinManager->createInstance('standard', $last_name_join_definition);
    $last_name_alias = $this->query->addTable('user__field_last_name', NULL, $last_name_join);

    // Join the table to get the machine user name.
    $username_join_definition = [
      'left_table' => $members_alias,
      'left_field' => 'members_target_id',
      'table' => 'users_field_data',
      'field' => 'uid',
    ];
    $username_join = $this->joinManager->createInstance('standard', $username_join_definition);
    $username_alias = $this->query->addTable('users_field_data', NULL, $username_join);

    // Join the table to get the discussion subject.
    $subject_join_definition = [
      'left_table' => $this->tableAlias,
      'left_field' => $this->realField,
      'table' => 'private_message_thread__field_pm_subject',
      'field' => 'entity_id',
    ];
    $subject_join = $this->joinManager->createInstance('standard', $subject_join_definition);
    $subject_alias = $this->query->addTable('private_message_thread__field_pm_subject', NULL, $subject_join);

    // Check if the given text is a part of the user first/last names, default
    // username of the discussion subject.
    $condition = new Condition('OR');
    $val = $this->connection->escapeLike($this->value);
    $condition->condition("$first_name_alias.field_first_name_value", "%$val%", 'LIKE');
    $condition->condition("$last_name_alias.field_last_name_value", "%$val%", 'LIKE');
    $condition->condition("$username_alias.name", "%$val%", 'LIKE');
    $condition->condition("$subject_alias.field_pm_subject_value", "%$val%", 'LIKE');
    $this->query->addWhere($this->options['group'], $condition);
  }

}

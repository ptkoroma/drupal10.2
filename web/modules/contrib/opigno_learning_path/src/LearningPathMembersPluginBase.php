<?php

namespace Drupal\opigno_learning_path;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\group\Entity\Group;
use Drupal\user\Entity\User;

/**
 * Class LearningPathMembersPluginBase.
 */
abstract class LearningPathMembersPluginBase extends PluginBase implements LearningPathMembersPluginInterface {

  /**
   * LearningPathMembersPluginBase constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getMembersForm(array &$form, FormStateInterface $form_state, User $current_user, \Closure $function, bool $hide = FALSE) {}

  /**
   * Returns group members uids.
   *
   * @param \Drupal\group\Entity\Group $group
   *   Group object.
   *
   * @return array|null
   *   Array of members uids if exist, null otherwise.
   */
  public function getGroupMembersIds(Group $group) {}

}

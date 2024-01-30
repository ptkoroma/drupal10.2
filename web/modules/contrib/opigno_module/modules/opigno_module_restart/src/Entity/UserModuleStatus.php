<?php

namespace Drupal\opigno_module_restart\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\opigno_module\Entity\UserModuleStatus as UserModuleStatusBase;
use Drupal\opigno_module\Entity\UserModuleStatusInterface;

/**
 * Overrides the default UserModuleStatus entity class definition.
 *
 * Adds count of restarted activities.
 *
 * @package Drupal\opigno_module_restart\Entity
 */
class UserModuleStatus extends UserModuleStatusBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['restarted_activities_number'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Restarted activities count'))
      ->setDescription(t('The number of times the activities inside the module had been restarted'))
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * Increments the number of restarted activities.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface
   *   The called user module status entity.
   */
  public function incrementRestartedActivitiesNumber(): UserModuleStatusInterface {
    if ($this->hasField('restarted_activities_number')) {
      $num = (int) $this->get('restarted_activities_number')->getString();
      $this->set('restarted_activities_number', $num + 1);
    }

    return $this;
  }

}

<?php

namespace Drupal\opigno_learning_path\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for LPStatus entities.
 */
class LPStatusViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['groups']['user_lp_status']['relationship'] = [
      'title' => t('User Learning Path status'),
      'label' => t('User Learning Path status relation'),
      'group' => t('Group content'),
      'help' => t('Relates to the User Learning Path Status entity the group content represents.'),
      'id' => 'standard',
      'base' => 'user_lp_status',
      'base field' => 'gid',
      'field' => 'id',
    ];

    return $data;
  }

}

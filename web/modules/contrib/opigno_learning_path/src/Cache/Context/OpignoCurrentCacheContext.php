<?php

namespace Drupal\opigno_learning_path\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Cache\Context\UserCacheContextBase;
use Drupal\opigno_learning_path\Traits\LearningPathAchievementTrait;

/**
 * Defines the OpignoCurrentCacheContext service.
 *
 * Cache context ID: 'opigno_current'.
 */
class OpignoCurrentCacheContext extends UserCacheContextBase implements CalculatedCacheContextInterface {

  use LearningPathAchievementTrait;

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Opigno current');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL) {
    switch ($parameter) {
      case 'group_id':
        return $this->getCurrentGroupId();

      case 'content_id':
        return $this->getCurrentGroupContentId();

      case 'activity_id':
        return $this->getCurrentActivityId();
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL) {
    return new CacheableMetadata();
  }

}

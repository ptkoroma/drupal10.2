<?php

namespace Drupal\opigno_moxtra;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for opigno_moxtra_meeting_result entity.
 */
class MeetingResultListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['meeting'] = $this->t('Live Meeting');
    $header['user'] = $this->t('User');
    $header['status'] = $this->t('Status');
    $header['score'] = $this->t('Score');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    if ($user = $entity->getUser()) {
      /* @var \Drupal\opigno_moxtra\MeetingResultInterface $entity */
      $row['id'] = $entity->id();
      $row['meeting'] = $entity->getMeeting()->toLink();
      $row['user'] = $user->toLink();
      $row['status'] = $entity->getStatusString();
      $row['score'] = $entity->getScore();
      return $row + parent::buildRow($entity);
    }
  }

}

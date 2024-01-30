<?php

namespace Drupal\opigno_module;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Activity entities.
 *
 * @ingroup opigno_module
 */
class OpignoActivityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Activity ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\opigno_module\Entity\OpignoActivity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute($entity->label(),'entity.opigno_activity.edit_form', ['opigno_activity' => $entity->id()]);
    return $row + parent::buildRow($entity);
  }

}

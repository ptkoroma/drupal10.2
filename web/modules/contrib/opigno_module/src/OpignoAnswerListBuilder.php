<?php

namespace Drupal\opigno_module;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Answer entities.
 *
 * @ingroup opigno_module
 */
class OpignoAnswerListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Answer ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\opigno_module\Entity\OpignoAnswer */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute($entity->id(),'entity.opigno_answer.edit_form', ['opigno_answer' => $entity->id()]);
    return $row + parent::buildRow($entity);
  }

}

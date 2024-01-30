<?php

namespace Drupal\opigno_learning_path\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the Learning Path Content entity.
 *
 * @ingroup opigno_learning_path
 *
 * @ContentEntityType(
 *   id = "learning_path_result",
 *   label = @Translation("Learning Path Result"),
 *   base_table = "learning_path_result",
 *   entity_keys = {
 *     "id" = "id",
 *     "learning_path_id" = "learning_path_id",
 *     "user_id" = "user_id",
 *     "has_passed" = "has_passed"
 *   },
 *   handlers = {
 *    "access" = "Drupal\opigno_learning_path\LPResultAccessControlHandler",
 *   }
 * )
 *
 * @deprecated in opigno:3.0.9 and is removed from opigno:3.1.0. Use the LPStatus entity instead.
 * @see https://www.drupal.org/project/opigno/issues/3090002
 */
class LPResult extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['learning_path_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Learning Path')
      ->setCardinality(1)
      ->setSetting('target_type', 'group')
      ->setSetting('handler_settings',
        [
          'target_bundles' => ['learning_path' => 'learning_path'],
        ]);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('User')
      ->setSetting('target_type', 'user');

    $fields['has_passed'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Has passed')
      ->setDescription('Define if the user has passed the learning path')
      ->setDefaultValue(FALSE);

    $fields['started'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Started'))
      ->setDescription(t('The time that the LP attempt has started.'));

    $fields['finished'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Finished'))
      ->setDescription(t('The time that the LP attempt finished.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Creation datetime'))
      ->setDescription(t('The time that the result was saved.'));

    return $fields;
  }

}

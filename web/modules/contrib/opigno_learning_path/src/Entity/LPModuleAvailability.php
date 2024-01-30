<?php

namespace Drupal\opigno_learning_path\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * The Learning Path Module Availability entity type definition.
 *
 * @ContentEntityType(
 *   id = "lp_module_availability",
 *   label = @Translation("Learning Path Module Availability"),
 *   base_table = "lp_module_availability",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "group_id" = "group_id",
 *     "entity_id" = "entity_id",
 *     "availability" = "availability",
 *     "open_date" = "open_date",
 *     "close_date" = "close_date"
 *   },
 * )
 */
class LPModuleAvailability extends ContentEntityBase {

  /**
   * Creates a new LPModuleAvailability object with the values passed in param.
   *
   * It's not saved automatically. You need to do $obj->save().
   *
   * @return \Drupal\Core\Entity\EntityInterface|self
   *   LPModuleAvailability object.
   */
  public static function createWithValues(
    $group_id,
    $entity_id,
    $availability = 0,
    $open_date = 0,
    $close_date = 0
  ) {
    $values = [
      'group_id' => $group_id,
      'entity_id' => $entity_id,
      'availability' => $availability,
      'open_date' => $open_date,
      'close_date' => $close_date,
    ];

    return parent::create($values);
  }

  /**
   * Loads one or more LPManagedContent by the properties.
   *
   * The available properties are the entity_keys
   * specified in the header of this LPManagedContent class.
   *
   * Best is to avoid to use this method
   * and create a specific method for your search,
   * like the method loadByLearningPathId.
   */
  public static function loadByProperties($properties) {
    return \Drupal::entityTypeManager()->getStorage('lp_module_availability')->loadByProperties($properties);
  }

  /**
   * Get training module availability restrict to training flag.
   */
  public function getAvailability() {
    $availability = $this->get('availability')->value;
    return isset($availability) ? $availability : 0;
  }

  /**
   * Get training module open date.
   */
  public function getOpenDate() {
    return $this->get('open_date')->value;
  }

  /**
   * Get training module close date.
   */
  public function getCloseDate() {
    return $this->get('close_date')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['id']->setLabel(t('Module availability ID'))
      ->setDescription(t('The LP module availability ID.'));

    $fields['group_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Group ID')
      ->setDescription('The group ID')
      ->setSetting('target_type', 'group')
      ->setSetting('handler_settings',
        [
          'target_bundles' => ['learning_path' => 'learning_path'],
        ]);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Entity ID')
      ->setDescription('The entity ID')
      ->setSettings([
        'target_type' => 'opigno_module',
        'handler' => 'default',
      ]);

    $options = [
      0 => t('Always available'),
      1 => t('Restrict to specific dates for that training'),
    ];
    $fields['availability'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Module availability'))
      ->setDescription(t('Set module availability for particular training.'))
      ->setSetting('allowed_values', $options)
      ->setDefaultValue(0);

    $fields['open_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Open date'))
      ->setDescription(t('The date this Module will become available.'));

    $fields['close_date'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Open date'))
      ->setDescription(t('The date this Module will become unavailable.'));

    return $fields;
  }

}

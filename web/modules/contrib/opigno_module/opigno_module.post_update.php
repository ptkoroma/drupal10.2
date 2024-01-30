<?php

/**
 * @file
 * Contains opigno module post update functions.
 */

use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchemaConverter;

/**
 * Update opigno_answer to be revisionable.
 */
function opigno_module_post_update_make_opigno_answer_revisionable(&$sandbox) {
  $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
  /** @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $last_installed_schema_repository */
  $last_installed_schema_repository = \Drupal::service('entity.last_installed_schema.repository');

  $entity_type = $definition_update_manager->getEntityType('opigno_answer');
  $field_storage_definitions = $last_installed_schema_repository->getLastInstalledFieldStorageDefinitions('opigno_answer');

  // Update the entity type definition.
  $entity_keys = $entity_type->getKeys();
  $entity_keys['revision'] = 'revision_id';
  $entity_keys['revision_translation_affected'] = 'revision_translation_affected';
  $entity_type->set('entity_keys', $entity_keys);
  $entity_type->set('revision_table', 'opigno_answer_revision');
  $entity_type->set('revision_data_table', 'opigno_answer_field_revision');
  $revision_metadata_keys = [
    'revision_default' => 'revision_default',
    'revision_user' => 'revision_user',
    'revision_created' => 'revision_created',
    'revision_log_message' => 'revision_log_message',
  ];
  $entity_type->set('revision_metadata_keys', $revision_metadata_keys);

  // Update the field storage definitions.
  $field_storage_definitions['langcode']->setRevisionable(TRUE);
  $field_storage_definitions['default_langcode']->setRevisionable(TRUE);
  $field_storage_definitions['user_id']->setRevisionable(TRUE);

  \Drupal::entityDefinitionUpdateManager()->updateFieldableEntityType($entity_type, $field_storage_definitions,$sandbox);
}

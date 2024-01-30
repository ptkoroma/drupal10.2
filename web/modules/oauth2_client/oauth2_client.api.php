<?php

/**
 * @file
 * Documents hooks provided by the OAuth2 Client module.
 */

/**
 * Alter OAuth2 Client plugin definitions.
 *
 * @param array $definitions
 *   An array of OAuth2 Client plugins registered on the system.
 *
 * @see \Drupal\Core\Plugin\DefaultPluginManager::alterDefinitions
 */
function hook_oauth2_client_info_alter(array &$definitions) {
  $oauth2_clients['some_id']['some_key'] = 'some_value';
}

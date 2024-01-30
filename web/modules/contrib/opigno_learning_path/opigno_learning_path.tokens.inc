<?php

/**
 * @file
 * Builds placeholder replacement tokens for opigno_certificate-related data.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\opigno_learning_path\Entity\LPStatus;

/**
 * Implements hook_token_info().
 */
function opigno_learning_path_token_info() {
  $info['tokens']['group']['expiration_date'] = [
    'name' => t('Certificate expiration date'),
    'description' => t('The training group certificate expiration date.'),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function opigno_learning_path_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'group') {
    foreach ($tokens as $name => $original) {
      if ($name == 'expiration_date') {
        $type = '';
        if (!empty($data["group"]) && !empty($data["uid"])) {
          $group = $data["group"];
          $uid = $data["uid"];
          $type = !empty($data["expire_text_type"]) ? $data["expire_text_type"] : NULL;
        }
        elseif (!empty($data['opigno_certificate']->referencing_entity->entity)) {
          $group = $data['opigno_certificate']->referencing_entity->entity;
          $uid = \Drupal::currentUser()->id();
          $type = 'valid';
        }

        if (!empty($group) && !empty($uid) && $group->bundle() == 'learning_path') {
          $replacements[$original] = LPStatus::getCertificateExpirationMessage($group->id(), $uid, $type);
        }
      }
    }
  }

  return $replacements;
}

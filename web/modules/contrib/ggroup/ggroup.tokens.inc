<?php

/**
 * @file
 * Builds placeholder replacement tokens for group group-related data.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupInterface;

/**
 * Implements hook_token_info_alter().
 */
function ggroup_token_info_alter(&$info) {
  $info['tokens']['group']['url']['type'] = 'url';
}

/**
 * Implements hook_token_info().
 */
function ggroup_token_info() {
  $tokens = [];

  $tokens['group'] = [
    'name' => t('Group'),
    'description' => t('The parent group.'),
    'type' => 'group',
  ];

  if (\Drupal::moduleHandler()->moduleExists('token')) {
    $tokens['groups'] = [
      'name' => t('Groups'),
      'description' => t('An array of all the group parent groups.'),
      'type' => 'array',
    ];
  }

  return [
    'tokens' => ['group' => $tokens],
  ];
}

/**
 * Implements hook_tokens().
 */
function ggroup_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  if ($type != 'group' || empty($data['group']) || empty($data['group']->id())) {
    return [];
  }

  $token_service = \Drupal::token();
  $replacements = [];

  $group_content_array = GroupContent::loadByEntity($data['group']);
  if (empty($group_content_array)) {
    return [];
  }

  $groups = [];
  /** @var \Drupal\group\Entity\GroupContentInterface $group_content */
  foreach ($group_content_array as $group_content) {
    $group = $group_content->getGroup();
    if ($group instanceof GroupInterface) {
      $groups[$group->id()] = $group->label();
      $bubbleable_metadata->addCacheableDependency($group);
    }
  };

  if (isset($tokens['groups'])) {
    $replacements[$tokens['groups']] = token_render_array($groups, $options);
  }

  // [group:groups:*] chained tokens.
  if ($parents_tokens = $token_service->findWithPrefix($tokens, 'groups')) {
    $replacements += $token_service->generate('array', $parents_tokens, ['array' => $groups], $options, $bubbleable_metadata);
  }

  $group_content = array_pop($group_content_array);
  $group = $group_content->getGroup();
  if ($group instanceof GroupInterface) {
    if (isset($tokens['group'])) {
      $replacements[$tokens['group']] = $group->label();
    }

    $langcode = $data['group']->language()->getId();
    if ($group->hasTranslation($langcode)) {
      $group = $group->getTranslation($langcode);
    }

    if ($group_tokens = $token_service->findWithPrefix($tokens, 'group')) {
      $replacements += $token_service->generate('group', $group_tokens, ['group' => $group], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}

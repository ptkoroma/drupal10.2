<?php

/**
 * @file
 * Contains extra hooks for Opigno calendar views.
 */

/**
 * Implements hook_views_data().
 */
function opigno_calendar_views_data() {
  $data['views']['opigno_calendar_region'] = [
    'title' => t('Opigno calendar region'),
    'help' => t('Opigno calendar region for add event link'),
    'area' => [
      'id' => 'opigno_calendar_region',
    ],
  ];

  $data['views']['opigno_calendar_today'] = [
    'title' => t('Opigno calendar today'),
    'help' => t('Opigno calendar region for today button'),
    'area' => [
      'id' => 'opigno_calendar_today',
      'access callback' => 'opigno_calendar_region_access_callback',
    ],
  ];
  return $data;
}

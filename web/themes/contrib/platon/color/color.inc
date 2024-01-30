<?php
$info = [
  // Available colors and color labels used in theme.
  'fields' => [
    'primary_button_color' => t('Primary Button Color'),
    'primary_button_hover_color' => t('Primary Button Hover Color'),
    'primary_button_text_color' => t('Primary Button Text Color'),
    'primary_button_hover_text_color' => t('Primary Button Hover Text Color'),
    'primary_theme_color' => t('Primary Theme Color'),
    'active_link_color' => t('Active Link Color'),
    'header_background_color' => t('Header Background Color'),
    'navigation_background_color' => t('Navigation Background Color'),
  ],
  // Pre-defined color schemes.
  'schemes' => [
    'default' => [
      'title' => t('Default'),
      'colors' => [
        'primary_button_color' => '#c2e76b',
        'primary_button_hover_color' => '#b4e24a',
        'primary_button_text_color' => '#ffffff',
        'primary_button_hover_text_color' => '#ffffff',
        'primary_theme_color' => '#5bb4d8',
        'active_link_color' => '#0287bf',
        'header_background_color' => '#3c3c3b',
        'navigation_background_color' => '#f8f7f5',
      ],
    ]
  ]
];
// CSS files (excluding @import) to rewrite with new color scheme.
$info['css'] = ['color/color.css'];

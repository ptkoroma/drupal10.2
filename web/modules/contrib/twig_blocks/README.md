# Twig Blocks

* Introduction
* Installation
* Maintainers

### Introduction

The Twig Blocks module adds a twig helper function to render a block
in a twig template.

### Installation

Install as you would normally install a contributed Drupal module.

See: https://drupal.org/documentation/install/modules-themes/modules-8
for further information.

### Usage

The simplest way to render block plugin is as follows.
```twig
{{ render_block('block_id') }}
```

Optionally you can pass block label and plugin configuration in the second
parameter.
```twig
{{ render_block('block_id', {label: 'Example'|t, some_setting: 'example', setting_array: {value: value}}) }}
```

### Maintainers

* Adam (hook_awesome) - https://www.drupal.org/user/2802921
* George Anderson (geoanders) - https://www.drupal.org/u/geoanders

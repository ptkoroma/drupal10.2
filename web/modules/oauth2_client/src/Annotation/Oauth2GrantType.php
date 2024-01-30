<?php

declare(strict_types = 1);

namespace Drupal\oauth2_client\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines oauth2_grant_type annotation object.
 *
 * @Annotation
 */
class Oauth2GrantType extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $title;

  /**
   * The description of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $description;

}

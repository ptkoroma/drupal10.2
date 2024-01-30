<?php

namespace Drupal\opigno_social_community\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the entity title is unique.
 *
 * @Constraint(
 *   id = "opigno_unique_title",
 *   label = @Translation("Unique title", context = "Validation"),
 *   type = "string"
 * )
 */
class UniqueTitle extends Constraint {

  /**
   * The message that will be shown if the entity title value is duplicated.
   *
   * @var string
   */
  public string $message = "%entity_type with the same title already exists. A title must be unique.";

}

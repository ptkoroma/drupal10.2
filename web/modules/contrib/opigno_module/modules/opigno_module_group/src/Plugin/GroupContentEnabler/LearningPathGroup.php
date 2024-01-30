<?php

namespace Drupal\opigno_module_group\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Allows learning_path content to be added to groups.
 *
 * @GroupContentEnabler(
 *   id = "subgroup:learning_path",
 *   label = @Translation("Learning path"),
 *   description = @Translation("Adds Learning path groups to groups both publicly and privately."),
 *   entity_type_id = "group",
 *   pretty_path_key = "group",
 *   reference_label = @Translation("Learning path"),
 *   reference_description = @Translation("Adds Learning path groups to groups both publicly and privately.")
 * )
 */
class LearningPathGroup extends GroupContentEnablerBase {

}

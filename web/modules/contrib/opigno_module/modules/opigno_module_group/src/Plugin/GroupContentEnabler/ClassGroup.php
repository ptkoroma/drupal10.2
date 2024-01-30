<?php

namespace Drupal\opigno_module_group\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Allows opigno_class content to be added to groups.
 *
 * @GroupContentEnabler(
 *   id = "subgroup:opigno_class",
 *   label = @Translation("Class Group"),
 *   description = @Translation("Adds Class groups to groups both publicly and privately."),
 *   entity_type_id = "group",
 *   pretty_path_key = "group",
 *   reference_label = @Translation("Class"),
 *   reference_description = @Translation("Adds Class groups to groups both publicly and privately.")
 * )
 */
class ClassGroup extends GroupContentEnablerBase {

}

<?php

namespace Drupal\opigno_module_group\Plugin\GroupContentEnabler;

use Drupal\group\Plugin\GroupContentEnablerBase;

/**
 * Allows opigno_course content to be added to groups.
 *
 * @GroupContentEnabler(
 *   id = "subgroup:opigno_course",
 *   label = @Translation("Course"),
 *   description = @Translation("Adds Course groups to groups both publicly and privately."),
 *   entity_type_id = "group",
 *   pretty_path_key = "group",
 *   reference_label = @Translation("Course"),
 *   reference_description = @Translation("Adds Course groups to groups both publicly and privately.")
 * )
 */
class CourseGroup extends GroupContentEnablerBase {

}

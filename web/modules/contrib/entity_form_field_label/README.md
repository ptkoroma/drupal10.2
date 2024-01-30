# Entity Form Field Label Module

## CONTENTS OF THIS FILE

 - Introduction
 - Requirements
 - Installation
 - Configuration
 - Maintainers


## INTRODUCTION

Entity Form Field Label Adds an ability to change a displayed label for an entity field.

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/entity_form_field_label

 * To submit bug reports and feature suggestions, or to track changes:
   https://www.drupal.org/project/issues/search/entity_form_field_label


## REQUIREMENTS

No special requirements.


## INSTALLATION

 * Install as you would normally install a contributed Drupal module.
   See: https://www.drupal.org/docs/extending-drupal/installing-modules for further information.


## CONFIGURATION

**Can be used for [Display Modes](https://www.drupal.org/project/entity_form_field_label/issues/3109614)**
(>= 8.x-1.4 version).

**Can be used in [layout builder](https://www.drupal.org/project/entity_form_field_label/issues/3265577)**
(>= 8.x-1.6 version).


- **For instance:**<br>
You have an entity with a few Form/Display Modes and this entity has a field which is called "Attachments". But you want this field to have different labels on each Form/Display mode. Like: "Documents", "Files", "Attach file", etc. <br>You can do it using this module.

- **How to use:**<br>
Go to the Form (or Display) Mode settings page
Set the "Rewrite label" checkbox. An additional form element ("New label") will appear.
Write down the new label to the "New label" input OR leave it empty for removing field label.

- **Attention!**<br>
Some specific fields may not be supported. But it shouldn't be hard to correct this. The module is really simple.
If the field is composed then use "||" as a separator. Example for the Date Range field: "Event Start Date||Event End Date"

## Similar Modules:

[Field Label](https://www.drupal.org/project/field_label) extends field formatters for most field types to allow customization of field label text, CSS classes and/or label wrapper tag at the display field formatter level (i.e. per content type, view mode, layout override, etc.).

## MAINTAINERS

Current maintainers:
 * Nikita Sineok (nikita_tt) - https://www.drupal.org/u/nikita_tt
 

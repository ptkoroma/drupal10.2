<?php

namespace Drupal\extra_field\Generators;

use DrupalCodeGenerator\Command\Plugin\PluginGenerator;

/**
 * Generator class for extra_field display plugin.
 */
class ExtraFieldDisplayV2 extends PluginGenerator {

  /**
   * The command name.
   *
   * @var string
   */
  protected string $name = 'plugin:extra-field-display';

  /**
   * The command description.
   *
   * @var string
   */
  protected string $description = 'Generates an extra field display plugin.';

  /**
   * The command alias.
   *
   * @var string
   */
  protected string $alias = 'extra-field-display';

  /**
   * A path where templates are stored.
   *
   * @var string
   */
  protected string $templatePath = __DIR__;

  /**
   * {@inheritdoc}
   */
  public function generate(array &$vars): void {

    $this->collectDefault($vars);
    $this->collectServices($vars, FALSE);

    $this->addFile('src/Plugin/ExtraField/Display/{class}.php', 'extra-field-display.twig');
  }

}

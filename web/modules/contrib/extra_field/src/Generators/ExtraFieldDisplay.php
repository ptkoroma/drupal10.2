<?php

namespace Drupal\extra_field\Generators;

use DrupalCodeGenerator\Command\BaseGenerator;
use DrupalCodeGenerator\Utils;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Generator class for extra_field display plugin.
 */
class ExtraFieldDisplay extends BaseGenerator {

  /**
   * The command name.
   *
   * @var string
   */
  protected $name = 'plugin:extra-field-display';

  /**
   * The command description.
   *
   * @var string
   */
  protected $description = 'Generates an extra field display plugin.';

  /**
   * The command alias.
   *
   * @var string
   */
  protected $alias = 'extra-field-display';

  /**
   * A path where templates are stored.
   *
   * @var string
   */
  protected $templatePath = __DIR__;

  /**
   * {@inheritdoc}
   */
  protected function interact(InputInterface $input, OutputInterface $output) {
    $questions = Utils::moduleQuestions();
    $questions += Utils::pluginQuestions('');
    $this->collectVars($input, $output, $questions);

    $di_question = new ConfirmationQuestion('Would you like to inject dependencies?', FALSE);
    if ($this->ask($input, $output, $di_question)) {
      $this->collectServices($input, $output);
    }

    $this->addFile()
      ->path('src/Plugin/ExtraField/Display/{class}.php')
      ->template('extra-field-display.twig');
  }

}

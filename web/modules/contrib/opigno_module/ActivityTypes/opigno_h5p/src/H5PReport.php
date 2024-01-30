<?php

namespace Drupal\opigno_h5p;

/**
 * Defines the H5PReport generator class.
 *
 * @package Drupal\opigno_h5p
 */
class H5PReport {

  /**
   * The H5P version.
   *
   * @var string
   */
  private static string $version = '1.1.0';

  /**
   * The processor mapping.
   *
   * @var array|string[]
   */
  private static array $processorMap = [
    'compound' => 'Drupal\opigno_h5p\TypeProcessors\CompoundProcessor',
    'fill-in' => 'Drupal\opigno_h5p\TypeProcessors\FillInProcessor',
    'long-fill-in' => 'Drupal\opigno_h5p\TypeProcessors\FillInProcessor',
    'true-false' => 'Drupal\opigno_h5p\TypeProcessors\TrueFalseProcessor',
    'matching' => 'Drupal\opigno_h5p\TypeProcessors\MatchingProcessor',
    'choice' => 'Drupal\opigno_h5p\TypeProcessors\ChoiceProcessor',
    'long-choice' => 'Drupal\opigno_h5p\TypeProcessors\LongChoiceProcessor',
  ];

  /**
   * The H5P version extension url.
   *
   * @var string
   */
  private static string $versionExtension = 'https://h5p.org/x-api/h5p-reporting-version';

  /**
   * The H5P content type extension url.
   *
   * @var string
   */
  private static string $contentTypeExtension = 'https://h5p.org/x-api/h5p-machine-name';

  /**
   * The list of content type processors.
   *
   * @var string[]
   */
  public static array $contentTypeProcessors = [
    'H5P.DocumentationTool' => 'DocumentationToolProcessor',
    'H5P.GoalsPage' => 'GoalsPageProcessor',
    'H5P.GoalsAssessmentPage' => 'GoalsAssessmentPageProcessor',
    'H5P.StandardPage' => 'StandardPageProcessor',
    'H5P.FreeTextQuestion' => 'IVOpenEndedQuestionProcessor',
  ];

  /**
   * The list of used processors.
   *
   * @var array
   */
  private array $processors = [];

  /**
   * Generate the proper report depending on xAPI data.
   *
   * @param object $xapiData
   *   The xAPI data.
   * @param string|null $forcedProcessor
   *   Force a processor type.
   * @param bool $disableScoring
   *   Disables scoring for the report.
   *
   * @return string
   *   A report.
   */
  public function generateReport(object $xapiData, ?string $forcedProcessor = NULL, bool $disableScoring = FALSE): string {
    $interactionType = $xapiData->interaction_type;
    if (!self::isSupportedVersion($xapiData)) {
      return self::renderUnsupportedVersionPage($xapiData);
    }

    $contentTypeProcessor = self::getContentTypeProcessor($xapiData);
    if (isset($contentTypeProcessor)) {
      $interactionType = $contentTypeProcessor;
    }

    if (isset($forcedProcessor)) {
      $interactionType = $forcedProcessor;
    }

    if (!isset(self::$processorMap[$interactionType]) && !isset(self::$contentTypeProcessors[$interactionType])) {
      // No processor found.
      return '';
    }

    if (!isset($this->processors[$interactionType])) {
      // Not used before. Initialize new processor.
      $processor = self::$processorMap[$interactionType];
      $this->processors[$interactionType] = new $processor();

      if (array_key_exists($interactionType, self::$contentTypeProcessors)) {
        $this->processors[$interactionType] = new self::$contentTypeProcessors[$interactionType]();
      }
      else {
        $this->processors[$interactionType] = new self::$processorMap[$interactionType]();
      }
    }

    // Generate and return report from xAPI data.
    // Allow compound content types to have styles
    // in case they are rendering gradable containers.
    return $this->processors[$interactionType]
      ->generateReport($xapiData, $disableScoring, ($interactionType == "compound"));
  }

  /**
   * Generate the report depending on xAPI data.
   *
   * @param object $xapiData
   *   The xAPI data.
   *
   * @return string
   *   A report.
   */
  public function generateGradableReports(object $xapiData): string {
    $results = [];

    foreach ($xapiData as $childData) {
      $interactionType = self::getContentTypeProcessor($childData);

      // Not used before. Initialize new processor.
      if (!isset($this->processors[$interactionType])
        && array_key_exists($interactionType, self::$contentTypeProcessors)
      ) {
        $this->processors[$interactionType] = new self::$contentTypeProcessors[$interactionType]();
      }

      if ($interactionType == 'H5P.FreeTextQuestion') {
        array_push($results, $childData);
      }
    }

    if (count($results) > 0) {
      return $this->buildContainer($results);
    }

    // Return nothing if there are no reports.
    return ' ';
  }

  /**
   * Generate the wrapping element for a grading container.
   *
   * @param array $results
   *   The list of elements in the container.
   *
   * @return string
   *   HTML of the container and within it, gradable elements.
   */
  private function buildContainer(array $results): string {
    $container = '<div id="gradable-container" class="h5p-iv-open-ended-grading-container">';

    foreach ($results as $child) {
      $container .= $this->buildChild($child);
    }

    $container .= '</div>';

    return $container;
  }

  /**
   * Generate each of the gradable elements.
   *
   * @param object $data
   *   The data to generate the HTML for the gradable element.
   *
   * @return string
   *   HTML of a gradable element.
   */
  private function buildChild(object $data): string {
    // Generate and return report from xAPI data.
    $interactionType = self::getContentTypeProcessor($data);
    return $this->processors[$interactionType]
      ->generateReport($data, FALSE, TRUE);
  }

  /**
   * List of CSS stylesheets used by the processors when rendering the report.
   *
   * @return array
   *   The list of CSS libraries that are used during the report rendering.
   */
  public function getStylesUsed(): array {
    $styles = [
      'styles/shared-styles.css',
    ];

    // Fetch style used by each report processor.
    foreach ($this->processors as $processor) {
      $style = $processor->getStyle();
      if (!empty($style)) {
        $styles[] = $style;
      }
    }

    return $styles;
  }

  /**
   * List of JS scripts to be used by the processors when rendering the report.
   *
   * @return array
   *   List of JS scripts that are used during the report rendering.
   */
  public function getScriptsUsed(): array {
    $scripts = [];

    // Fetch scripts used by each report processor.
    foreach ($this->processors as $processor) {
      $script = $processor->getScript();
      if (!empty($script)) {
        $scripts[] = $script;
      }
    }

    return $scripts;
  }

  /**
   * Caches instance of report generator.
   */
  public static function getInstance() {
    static $instance;

    if (!$instance) {
      $instance = new H5PReport();
    }

    return $instance;
  }

  /**
   * Attempts to retrieve content type processor from xapi data.
   *
   * @param object $xapiData
   *   The xAPI data.
   *
   * @return string|null
   *   Content type processor.
   */
  public static function getContentTypeProcessor(object $xapiData): ?string {
    if (!isset($xapiData->additionals)) {
      return NULL;
    }

    $extras = json_decode($xapiData->additionals);

    if (!isset($extras->extensions) || !isset($extras->extensions->{self::$contentTypeExtension})) {
      return NULL;
    }

    $processor = $extras->extensions->{self::$contentTypeExtension};
    if (!array_key_exists($processor, self::$contentTypeProcessors)) {
      return NULL;
    }

    return $processor;
  }

  /**
   * Get required reporting module version from statement.
   *
   * @param object $xapiData
   *   The xAPI data.
   *
   * @return string
   *   Defaults to 1.0.0.
   */
  public static function getVersion(object $xapiData): string {
    if (!isset($xapiData->additionals)) {
      return '1.0.0';
    }

    $additionals = json_decode($xapiData->additionals);

    return $additionals->contextExtensions->{self::$versionExtension} ?? '1.0.0';
  }

  /**
   * Check if render report from statement is supported.
   *
   * @param object $xapiData
   *   The xAPI data.
   *
   * @return bool
   *   Whether the current version is supported or not.
   */
  public static function isSupportedVersion(object $xapiData): bool {
    $reportingVersion = array_map('intval', explode('.', self::$version));
    $statementVersion = array_map('intval', explode('.', self::getVersion($xapiData)));

    // Sanitation and major version check.
    if (count($statementVersion) !== 3 || $reportingVersion[0] < $statementVersion[0]) {
      return FALSE;
    }

    // Check minor version.
    $hasOutdatedMinorVersion = $reportingVersion[0] === $statementVersion[0]
      && $reportingVersion[1] < $statementVersion[1];
    if ($hasOutdatedMinorVersion) {
      return FALSE;
    }

    // Patch versions are assumed to be compatible.
    return TRUE;
  }

  /**
   * Display message saying that report could not be rendered.
   *
   * @param object $xapiData
   *   The xAPI data.
   *
   * @return string
   *   The rendered markup for the message.
   */
  public static function renderUnsupportedVersionPage(object $xapiData): string {
    $text = t('Version @required of the reporting module is required to render this report. Currently installed: @current', [
      '@required' => self::getVersion($xapiData),
      '@current' => self::$version,
    ])->render();

    return "<div>$text</div>";
  }

}

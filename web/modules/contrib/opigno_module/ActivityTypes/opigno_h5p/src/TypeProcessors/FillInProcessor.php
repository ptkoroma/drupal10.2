<?php

namespace Drupal\opigno_h5p\TypeProcessors;

/**
 * Class FillInProcessor.
 *
 * Processes and generates HTML report for 'fill-in' interaction type.
 */
class FillInProcessor extends TypeProcessor {

  /**
   * Placeholder for answers in the description.
   *
   * 10 underscores.
   */
  const FILL_IN_PLACEHOLDER = '__________';

  /**
   * Separating between different answers and in user response.
   */
  const RESPONSES_SEPARATOR = '[,]';

  /**
   * Separator that will be applied between correct responses pattern words.
   */
  const CRP_REPORT_SEPARATOR = ' / ';

  /**
   * Support for the Alternatives extension in the x-api event.
   */
  const CONTENT_TYPE_ALTERNATIVES = 'https://h5p.org/x-api/alternatives';

  /**
   * Support the Case Sensitivity extension in the x-api event.
   */
  const CONTENT_TYPE_CASE_SENSITIVITY = 'https://h5p.org/x-api/case-sensitivity';

  /**
   * {@inheritdoc}
   */
  public function generateHtml(
    string $description,
    ?array $crp,
    string $response,
    ?object $extras,
    object $scoreSettings = NULL
  ): string {
    // We need some style for our report.
    $this->setStyle('styles/fill-in.css');

    if (isset($extras->extensions->{self::CONTENT_TYPE_CASE_SENSITIVITY}) &&
      isset($extras->extensions->{self::CONTENT_TYPE_ALTERNATIVES})) {
      $caseMatters = ['caseSensitive' => $extras->extensions->{self::CONTENT_TYPE_CASE_SENSITIVITY}];
      $processedCRPs = $extras->extensions->{self::CONTENT_TYPE_ALTERNATIVES};
    }
    else {
      // Generate interaction options.
      $caseMatters = $this->determineCaseMatters(empty($crp[0]) ? '' : $crp[0]);

      // Process correct responses.
      $processedCRPs = $this->processCrps($crp, $caseMatters['nextIndex']);
    }

    // Process user responses patterns.
    $processedResponse = $this->processResponse($response);

    // Build report from description, correct responses and user responses.
    $report = $this->buildReportOutput($description,
      $processedCRPs,
      $processedResponse,
      $caseMatters['caseSensitive'] ?? FALSE
    );

    $header = $this->generateHeader($scoreSettings);
    $longFillIn = property_exists($extras, 'longfillin') ? ' h5p-long-fill-in' : '';
    $container =
      '<div class="h5p-reporting-container h5p-fill-in-container' . $longFillIn . '">' .
      $header . $report .
      '</div>';

    // Footer only required if there is a correct responses pattern.
    $footer = (isset($crp)) ? $this->generateFooter() : '';

    return $container . $footer;
  }

  /**
   * Generate header element.
   */
  private function generateHeader($scoreSettings) {
    $scoreHtml = $this->generateScoreHtml($scoreSettings);

    return "<div class='h5p-fill-in-header'>" .
      $scoreHtml .
      "</div>";
  }

  /**
   * Generate footer.
   */
  public function generateFooter() {
    return '<div class="h5p-fill-in-footer">' .
      '<span class="h5p-fill-in-correct-responses-pattern">Correct Answer</span>' .
      '<span class="h5p-fill-in-user-response-correct">Your correct answer</span>' .
      '<span class="h5p-fill-in-user-response-wrong">Your incorrect answer</span>' .
      '</div>';
  }

  /**
   * Massages correct responses patterns data.
   *
   * The result is a two dimensional array sorted on placeholder order.
   *
   * @param array|null $crp
   *   Correct responses pattern.
   * @param int $strStartIndex
   *   Start index of actual response pattern.
   *   Any data before this index is options applied to the tasks,
   *   and should not be processed as part of the correct responses pattern.
   *
   * @return array
   *   Two dimensional array.
   *   The first array dimensions is sorted on placeholder order,
   *   and the second separates between correct answer alternatives.
   */
  private function processCrps(?array $crp, int $strStartIndex): array {

    // CRPs sorted by placeholder order.
    $sortedCRP = [];
    if (!is_array($crp)) {
      return $sortedCRP;
    }

    foreach ($crp as $crpString) {

      // Remove options.
      $pattern = substr($crpString, $strStartIndex);

      // Process correct responses pattern into array.
      $answers = explode(self::RESPONSES_SEPARATOR, $pattern);
      foreach ($answers as $index => $value) {

        // Create array of correct alternatives at placeholder index.
        if (!isset($sortedCRP[$index])) {
          $sortedCRP[$index] = [];
        }

        // Add alternative to placeholder index.
        if (!in_array($value, $sortedCRP[$index])) {
          $sortedCRP[$index][] = $value;
        }
      }
    }
    return $sortedCRP;
  }

  /**
   * Determine if interaction answer is case sensitive.
   *
   * @param string $singleCRP
   *   A correct responses pattern with encoded option.
   *
   * @return array
   *   Case sensitivity data.
   */
  private function determineCaseMatters($singleCRP) {
    $html          = '';
    $nextIndex     = 0;
    $caseSensitive = NULL;

    // Check if interaction has case sensitivity option as first option.
    if (strtolower(substr($singleCRP, 1, 13)) === 'case_matters=') {
      if (strtolower(substr($singleCRP, 14, 5)) === 'false') {
        $html          = 'caseSensitive = false';
        $nextIndex     = 20;
        $caseSensitive = FALSE;
      }
      elseif (strtolower(substr($singleCRP, 14, 4)) === 'true') {
        $html          = 'caseSensitive = true';
        $nextIndex     = 19;
        $caseSensitive = TRUE;
      }
    }

    return [
      'html' => $html,
      'nextIndex' => $nextIndex,
      'caseSensitive' => $caseSensitive,
    ];
  }

  /**
   * Build report.
   *
   * Creates a stylable HTML report from description user responses and correct
   * responses.
   *
   * @param string $description
   *   Description.
   * @param array|null $crp
   *   Correct responses pattern.
   * @param array $response
   *   User responses.
   * @param bool $caseSensitive
   *   Case sensitivity of interaction.
   *
   * @return string
   *   The report output.
   */
  private function buildReportOutput(
    string $description,
    ?array $crp,
    array $response,
    bool $caseSensitive
  ): string {
    // Get placeholder replacements and replace them.
    $placeholderReplacements = $this->getPlaceholderReplacements($crp,
      $response,
      $caseSensitive
    );
    return $this->replacePlaceholders($description, $placeholderReplacements);
  }

  /**
   * Format user answer to replace placeholders in description.
   *
   * @param array|null $crp
   *   Correct responses patterns.
   * @param array $response
   *   User responses.
   * @param bool $caseSensitive
   *   Case sensitivity of interaction.
   *
   * @return array
   *   Placeholder replacements.
   */
  private function getPlaceholderReplacements(?array $crp, array $response, bool $caseSensitive): array {
    $placeholderReplacements = [];

    // Return response without markup if answers are neither right nor wrong.
    if (!$crp) {
      foreach ($response as $answer) {
        $placeholderReplacements[] =
          '<span class="h5p-fill-in-user-response h5p-fill-in-user-response-correct h5p-fill-in-no-correct">' .
          nl2br($answer) .
          '</span>';
      }
    }

    foreach ($crp as $index => $value) {
      $currentResponse = $response[$index] ?? '';

      // Determine user response styling.
      $isCorrect = $this->isResponseCorrect($currentResponse,
        $value,
        $caseSensitive
      );
      $responseClass = $isCorrect ?
        'h5p-fill-in-user-response-correct' :
        'h5p-fill-in-user-response-wrong';

      // Format the placeholder replacements.
      $userResponse =
        '<span class="h5p-fill-in-user-response ' . $responseClass . '">' .
        $currentResponse .
        '</span>';

      $crpHtml = $this->getCrpHtml($value, $currentResponse, $caseSensitive);

      $correctResponsePattern = '';
      if (strlen($crpHtml) > 0) {
        $correctResponsePattern .=
          '<span class="h5p-fill-in-correct-responses-pattern">' .
          $crpHtml .
          '</span>';
      }

      $placeholderReplacements[] = $userResponse . $correctResponsePattern;
    }

    return $placeholderReplacements;
  }

  /**
   * Generate HTML from a single correct response pattern.
   *
   * @param array $singleCRP
   *   A single correct pattern to check.
   * @param string $response
   *   User response.
   * @param bool $caseSensitive
   *   The case sensitivity.
   *
   * @return string
   *   The rendered CRP html.
   */
  private function getCrpHtml(array $singleCRP, string $response, bool $caseSensitive): string {
    $html = [];

    foreach ($singleCRP as $value) {

      // Compare lower cases if not case sensitive.
      $comparisonCRP = $value;
      $comparisonResponse = $response;
      if (isset($caseSensitive) && $caseSensitive === FALSE) {
        $comparisonCRP = strtolower($value);
        $comparisonResponse = strtolower($response);
      }

      // Skip showing answers that user gave.
      if ($comparisonCRP === $comparisonResponse) {
        continue;
      }

      $html[] = $value;
    }

    return implode(self::CRP_REPORT_SEPARATOR, $html);
  }

  /**
   * Check if the answer correct by matching it with the correct pattern.
   *
   * @param string $response
   *   User response.
   * @param string $crp
   *   Correct responses pattern.
   * @param bool $caseSensitive
   *   Case sensitivity.
   *
   * @return bool
   *   TRUE if the user's response is correct, FALSE otherwise.
   */
  private function isResponseCorrect(string $response, string $crp, bool $caseSensitive): bool {
    $userResponse = $response;
    $matchingPattern = $crp;

    // Make user response and matching pattern lower case if case insensitive.
    if (isset($caseSensitive) && $caseSensitive === FALSE) {
      $userResponse = strtolower($response);
      $matchingPattern = array_map('strtolower', $crp);
    }

    return in_array($userResponse, $matchingPattern);
  }

  /**
   * Process response by dividing it into an array on response separators.
   *
   * @param string $response
   *   User response.
   *
   * @return array
   *   List of user responses for the different fill-ins.
   */
  private function processResponse(string $response): array {
    return explode(self::RESPONSES_SEPARATOR, $response);
  }

  /**
   * Fill in description placeholders with replacements.
   *
   * @param string $description
   *   The description text.
   * @param array $placeholderReplacements
   *   Replacements for placeholders in the description.
   *
   * @return string
   *   Description with replaced placeholders.
   */
  private function replacePlaceholders(string $description, array $placeholderReplacements): string {
    $replacedDescription = $description;

    // Determine position of next placeholder and the corresponding
    // replacement index.
    $index   = 0;
    $nextPos = strpos($replacedDescription, self::FILL_IN_PLACEHOLDER, 0);

    while ($nextPos !== FALSE) {
      // Fill in placeholder in description with replacement.
      $replacedDescription = substr_replace(
        $replacedDescription,
        $placeholderReplacements[$index],
        $nextPos,
        strlen(self::FILL_IN_PLACEHOLDER)
      );

      // Determine position of next placeholder and the corresponding
      // replacement index.
      $nextPos = strpos($replacedDescription, self::FILL_IN_PLACEHOLDER,
        $nextPos + strlen($placeholderReplacements[$index]));
      $index += 1;
    }

    return $replacedDescription;
  }

}

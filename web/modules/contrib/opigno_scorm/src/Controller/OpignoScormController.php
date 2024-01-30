<?php

namespace Drupal\opigno_scorm\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class OpignoScormController.
 */
class OpignoScormController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function scormIntegrateSco($opigno_scorm_sco) {
    $scorm_service = \Drupal::service('opigno_scorm.scorm');
    $sco = $scorm_service->scormLoadSco($opigno_scorm_sco);
    // Does the SCO have a launch property ?
    if (!empty($sco->launch)) {
      $query = [];

      // Load the SCO data.
      $scorm = $scorm_service->scormLoadById($sco->scorm_id);

      // Remove the URL parameters from the launch URL.
      if (!empty($sco->attributes['parameters'])) {
        $sco->launch .= $sco->attributes['parameters'];
      }
      $parts = explode('?', $sco->launch);
      $launch = array_shift($parts);

      if (!empty($parts)) {
        // Failsafe - in case a launch URL has 2 or more '?'.
        $parameters = implode('&', $parts);
      }

      // Get the SCO location on the filesystem.
      $sco_location = "{$scorm->extracted_dir}/$launch";
      $sco_path = \Drupal::service('file_url_generator')->generateAbsoluteString($sco_location);

      // Where there any parameters ? If so, prepare them for Drupal.
      if (!empty($parameters)) {
        foreach (explode('&', $parameters) as $param) {
          list($key, $value) = explode('=', $param);
          $query[$key] = !empty($value) ? $value : '';
        }

        if ($query) {
          $query = UrlHelper::buildQuery($query);
          $sco_path = $sco_path . '?' . $query;
        }
      }

      return new TrustedRedirectResponse($sco_path);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Scorm data commit method.
   */
  public function scormCommit($opigno_scorm_id, $opigno_scorm_sco_id) {
    $data = NULL;
    $data_content = $GLOBALS['request']->getContent();
    if (!empty($_POST['data'])) {
      $data = self::jsonDecodeValidated($_POST['data']);
    }
    elseif ($data_content) {
      $data = self::jsonDecodeValidated($data_content);
    }

    $json_schema_file = $data->scorm_version == '2004' ? 'api-2004.json' : 'api-12.json';

    $schema = json_decode(file_get_contents(
      \Drupal::service('extension.list.module')->getPath('opigno_scorm') . '/json-schema/' . $json_schema_file
    ));

    if (!empty($data)) {
      $validator = new Validator();
      $result = $validator->validate($data, $schema);
      if ($result->isValid() && !empty($data->cmi->interactions)) {
        $_SESSION['scorm_answer_results'] = [
          'opigno_scorm_id' => $opigno_scorm_id,
          'opigno_scorm_sco_id' => $opigno_scorm_sco_id,
          'data' => $data,
        ];
      }
      $scorm_service = \Drupal::service('opigno_scorm.scorm');
      $scorm = $scorm_service->scormLoadById($opigno_scorm_id);
      \Drupal::moduleHandler()->invokeAll('opigno_scorm_commit', [
        $scorm,
        $opigno_scorm_sco_id,
        $data,
      ]);
      return new JsonResponse(['success' => 1]);
    }
    else {
      return new JsonResponse(['error' => 1, 'message' => 'no data received']);
    }
  }

  /**
   * Decoding JSon data with the length and errors validation.
   *
   * @param string $data
   *   Decoded string of JSON data.
   *
   * @return mixed|null
   *   A valid JSON or empty string in case of error.
   */
  public static function jsonDecodeValidated(string $data, int $limit = 1, int $flags = JSON_THROW_ON_ERROR, int $depth = 512) {
    $size_is_valid = (!isset($data[$limit * 1024 * 1024]));
    try {
      if (!$size_is_valid) {
        throw new \Exception('Invalid data size.');
      }
      return json_decode($data, FALSE, $depth, $flags);
    }
    catch (\Exception | \JsonException $e) {
      return NULL;
    }
  }

}

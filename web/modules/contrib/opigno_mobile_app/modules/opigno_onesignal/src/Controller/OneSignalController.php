<?php

namespace Drupal\opigno_onesignal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class OneSignalController.
 */
class OneSignalController extends ControllerBase {
//
//  /**
//   * Storeplayerid.
//   *
//   * @param \Symfony\Component\HttpFoundation\Request $request
//   *
//   * @return string
//   *   Return Hello string.
//   */
//  public function storePlayerId(Request $request) {
//    $db_connection = \Drupal::database();
//    $data['op'] = $request->get('op');
//    $data['player_id'] = $request->get('player_id');
//
//    if (isset($data['op'])) {
//        if ($data['op'] == 'store' && $data['player_id']) {
//          // Store player id.
//          $query = $db_connection->insert('opigno_onesignal_users');
//          $query->fields(['player_id', 'uid']);
//          $query->values([$data['player_id'], $this->currentUser()->id()]);
//          $result = $query->execute()->fetchAll();
//        }
//        elseif ($data['op'] == 'delete') {
//          // Delete player id.
//          $query = $db_connection->delete('opigno_onesignal_users');
//          $query->condition('uid', $this->currentUser()->id());
//          $query->execute();
//          return new JsonResponse(NULL, Response::HTTP_OK);
//        }
//    }
//
//    if (isset($result) && !empty($result)) {
//      return new JsonResponse(NULL, Response::HTTP_OK);
//    }
//
//    return new JsonResponse(NULL, Response::HTTP_BAD_REQUEST);
//  }

}

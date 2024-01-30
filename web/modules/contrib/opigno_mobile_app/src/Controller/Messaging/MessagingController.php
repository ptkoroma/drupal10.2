<?php

namespace Drupal\opigno_mobile_app\Controller\Messaging;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\opigno_mobile_app\PrivateMessagesHandler;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\private_message\Entity\PrivateMessageThread;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * The messaging controller.
 */
class MessagingController extends ControllerBase {

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $serializerFormats = [];

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new UserAuthenticationController object.
   *
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    Serializer $serializer,
    array $serializer_formats,
    LoggerInterface $logger,
    TimeInterface $time
  ) {
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    $this->logger = $logger;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
      $formats = $container->getParameter('serializer.formats');
    }
    else {
      $formats = ['json'];
      $encoders = [new JsonEncoder()];
      $serializer = new Serializer([], $encoders);
    }

    return new static(
      $serializer,
      $formats,
      $container->get('logger.factory')->get('user'),
      $container->get('datetime.time')
    );
  }

  /**
   * Add new message to Private Message Thread for current user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThread $private_message_thread
   *   The message thread.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return JsonResponse object with created message info.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addNewMessageToTread(PrivateMessageThread $private_message_thread, Request $request): JsonResponse {
    $format = $this->getRequestFormat($request);

    $content = $request->getContent();
    // Decode post data.
    try {
      $data = $this->serializer->decode($content, $format);
      $data = $data['body'];
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Syntax error.');
    }

    if (!isset($data['message'])) {
      throw new BadRequestHttpException('Missing message.');
    }
    // Create a new message.
    $message = PrivateMessage::create([
      'message' => [
        'value' => $data['message'],
        'format' => 'basic_html',
      ],
    ]);
    $message->save();
    // Add the message to thread.
    $private_message_thread->addMessage($message);
    $private_message_thread->save();

    $response_data = [
      'thread_id' => $private_message_thread->id(),
      'id' => $message->id(),
      'owner' => [
        'uid' => $message->getOwnerId(),
        'name' => $message->getOwner()->getAccountName(),
        'user_picture' => opigno_mobile_app_get_user_picture($message->getOwner()),
      ],
      'message' => $message->getMessage(),
      'created' => $message->getCreatedTime(),
    ];

    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Add messages bulk to Private Message Thread for current user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThread $private_message_thread
   *   The message thread.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return JsonResponse object with created messages if success and array
   *   with unsaved messages otherwise.
   */
  public function addMessagesBulkToTread(PrivateMessageThread $private_message_thread, Request $request): JsonResponse {
    $response_data = [
      'message' => '',
      'items' => [],
      'unsuccess_items' => [],
    ];
    $format = $this->getRequestFormat($request);
    $content = $request->getContent();

    // Decode post data.
    try {
      $data = $this->serializer->decode($content, $format);
      $data = $data['body'];
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Syntax error.');
    }

    foreach ($data as $index => $item) {
      try {
        // Create a new message.
        $message = PrivateMessage::create([
          'message' => [
            'value' => $data['message'],
            'format' => 'basic_html',
          ],
          'created' => $item['created'],
        ]);
        $message->save();
        // Add the message to thread.
        $private_message_thread->addMessage($message);
        $private_message_thread->save();
        // Add new message to response data.
        $response_data['items'][] = [
          'thread_id' => $private_message_thread->id(),
          'id' => $message->id(),
          'owner' => [
            'uid' => $message->getOwnerId(),
            'name' => $message->getOwner()->getAccountName(),
            'user_picture' => opigno_mobile_app_get_user_picture($message->getOwner()),
          ],
          'message' => $message->getMessage(),
          'created' => $message->getCreatedTime(),
        ];
      }
      catch (EntityStorageException $e) {
        // Return unsaved messages.
        $response_data['message'] = 'These messages were not created.';
        $response_data['unsuccess_items'] = array_slice($data, $index);
        return new JsonResponse($response_data, Response::HTTP_INTERNAL_SERVER_ERROR);
      }
    }
    // Return empty array and code 204.
    $response_data['message'] = 'Success';
    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Get Private Message Thread info for current user.
   *
   * @param \Drupal\private_message\Entity\PrivateMessageThread $private_message_thread
   *   The message thread.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return JsonResponse object with created thread info.
   */
  public function getPrivateMessageThreadInfo(PrivateMessageThread $private_message_thread): JsonResponse {
    $response_data = [
      'message' => '',
      'data' => [],
    ];
    // Filter fields without access.
    if ($private_message_thread instanceof FieldableEntityInterface) {
      foreach ($private_message_thread as $field_name => $field) {
        /** @var \Drupal\Core\Field\FieldItemListInterface $field */
        $field_access = $field->access('view', NULL, TRUE);
        if (!$field_access->isAllowed()) {
          $private_message_thread->set($field_name, NULL);
        }
      }
    }

    // Get last message for tread.
    $messages = $private_message_thread->getMessages();
    if ($messages) {
      usort($messages, function ($a, $b) {
        /** @var \Drupal\private_message\Entity\PrivateMessage $a */
        /** @var \Drupal\private_message\Entity\PrivateMessage $b */
        return (int) ($a->getCreatedTime() < $b->getCreatedTime());
      });
      $last_message = reset($messages);
    }

    // Get info about members.
    $members = $private_message_thread->getMembers();
    $members_info = array_map(function ($member) {
      return [
        'uid' => $member->id(),
        'name' => $member->getAccountName(),
        'user_picture' => opigno_mobile_app_get_user_picture($member),
      ];
    }, $members);
    $unread_messages = PrivateMessagesHandler::getUnreadMessagesForThread($private_message_thread, $this->currentUser());
    // Build response data.
    $response_data['data'] = [
      'id' => $private_message_thread->id(),
      'subject' => $private_message_thread->field_pm_subject->value,
      'members' => $members_info,
      'updated' => $private_message_thread->getUpdatedTime(),
      'last_access_time' => $private_message_thread->getLastAccessTimestamp($this->currentUser()),
      'messages' => count($private_message_thread->getMessages()),
      'unread_messages' => count($unread_messages),
      'last_uid' => isset($last_message) ? $last_message->getOwnerId() : '',
    ];

    $response_data['message'] = 'Success';
    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Create a new Private Message Thread.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Return JsonResponse object with created thread info.
   */
  public function createPrivateMessageThread(Request $request): JsonResponse {
    $response_data = [
      'message' => '',
      'data' => [],
    ];
    $format = $this->getRequestFormat($request);
    $content = $request->getContent();

    // Decode post data.
    try {
      $data = $this->serializer->decode($content, $format);
      $data = $data['body'];
    }
    catch (\Exception $e) {
      throw new BadRequestHttpException('Syntax error.');
    }

    if (isset($data['thread']['members']) && !empty($data['thread']['members'])) {
      try {
        $private_message_thread = PrivateMessageThread::create();
        // Set thread subject.
        if (isset($data['thread']['subject'])) {
          $private_message_thread->set('field_pm_subject', $data['thread']['subject']);
        }
        // Add author as a member to thread.
        $private_message_thread->addMember($this->currentUser());
        // Add other users as a member to a thread.
        foreach ($data['thread']['members'] as $uid) {
          if (is_numeric($uid)) {
            $private_message_thread->addMemberById($uid);
          }
        }
        $private_message_thread->save();

        // Create messages and add to the created tread.
        $created_messages = [];
        if (isset($data['messages'])) {
          foreach ($data['messages'] as $item) {
            // Create a new message.
            $message = PrivateMessage::create([
              'message' => [
                'value' => $item['message'] ?? '',
                'format' => 'basic_html',
              ],
              'created' => (isset($item['created']) && !empty($item['created']))
                ? $item['created'] : $this->time->getRequestTime(),
            ]);
            $message->save();
            // Add the message to the thread.
            $private_message_thread->addMessage($message);
            $private_message_thread->save();
            // Add created message to a response data.
            $created_messages[] = [
              'thread_id' => $private_message_thread->id(),
              'id' => $message->id(),
              'owner' => [
                'uid' => $message->getOwnerId(),
                'name' => $message->getOwner()->getAccountName(),
                'user_picture' => opigno_mobile_app_get_user_picture($message->getOwner()),
              ],
              'message' => $message->getMessage(),
              'created' => $message->getCreatedTime(),
            ];
          }
        }
      }
      catch (EntityStorageException $e) {
        $response_data['message'] = 'Could not create entities.';
        return new JsonResponse($response_data, Response::HTTP_BAD_REQUEST);
      }
    }
    else {
      // Return unsaved messages.
      $response_data['message'] = "'members' field is required.";
      return new JsonResponse($response_data, Response::HTTP_BAD_REQUEST);
    }

    // Get info about members.
    $members = $private_message_thread->getMembers();
    $members_info = array_map(function ($member) {
      return [
        'uid' => $member->id(),
        'name' => $member->getAccountName(),
        'user_picture' => opigno_mobile_app_get_user_picture($member),
      ];
    }, $members);
    // Get unread messages.
    $unread_messages = PrivateMessagesHandler::getUnreadMessagesForThread($private_message_thread, $this->currentUser());
    // Build response data.
    $response_data['data'] = [
      'id' => $private_message_thread->id(),
      'subject' => isset($private_message_thread->field_pm_subject)
        ? $private_message_thread->field_pm_subject->value : '',
      'members' => $members_info,
      'updated' => $private_message_thread->getUpdatedTime(),
      'last_access_time' => $private_message_thread->getLastAccessTimestamp($this->currentUser()),
      'messages' => count($private_message_thread->getMessages()),
      'unread_messages' => count($unread_messages),
      'created_messages' => $created_messages,
      // Last user who created a message always will be a thread author id.
      'last_uid' => $this->currentUser()->id(),
    ];

    $response_data['message'] = 'Success';
    return new JsonResponse($response_data, Response::HTTP_OK);
  }

  /**
   * Gets the format of the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return string
   *   The format of the request.
   */
  protected function getRequestFormat(Request $request) {
    $format = $request->getRequestFormat();
    if (!in_array($format, $this->serializerFormats)) {
      throw new BadRequestHttpException("Unrecognized format: $format.");
    }
    return $format;
  }

}

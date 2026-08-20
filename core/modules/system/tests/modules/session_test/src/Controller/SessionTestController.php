<?php

declare(strict_types=1);

namespace Drupal\session_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\session_test\Session\TestSessionBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller providing page callbacks for session tests.
 */
class SessionTestController extends ControllerBase {

  /**
   * Prints the stored session value to the screen.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   */
  #[Route(
    path: '/session-test/get',
    name: 'session_test.get',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function get(Request $request): array {
    $value = $request->getSession()->get('session_test_value');
    return empty($value)
      ? []
      : ['#markup' => $this->t('The current value of the stored session variable is: %val', ['%val' => $value])];
  }

  /**
   * Prints the stored session value to the screen.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   */
  #[Route(
    path: '/session-test/get-from-session-object',
    name: 'session_test.get_from_session_object',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function getFromSessionObject(Request $request): array {
    $value = $request->getSession()->get("session_test_key");
    return empty($value)
      ? []
      : ['#markup' => $this->t('The current value of the stored session variable is: %val', ['%val' => $value])];
  }

  /**
   * Print the current session ID.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   */
  #[Route(
    path: '/session-test/id',
    name: 'session_test.id',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function getId(Request $request): array {
    // Set a value in session, so that SessionManager::save() will start
    // a session.
    $session = $request->getSession();
    $session->set('test', 'test');
    $session->save();

    return ['#markup' => 'session_id:' . session_id() . "\n"];
  }

  /**
   * Print the current session ID as read from the cookie.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  #[Route(
    path: '/session-test/id-from-cookie',
    name: 'session_test.id_from_cookie',
    requirements: ['_access' => 'TRUE'],
  )]
  public function getIdFromCookie(Request $request): array {
    return [
      '#markup' => 'session_id:' . $request->cookies->get(session_name()) . "\n",
      '#cache' => ['contexts' => ['cookies:' . session_name()]],
    ];
  }

  /**
   * Stores a value in 'session_test_value' session attribute.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $test_value
   *   A session value.
   */
  #[Route(
    path: '/session-test/set/{test_value}',
    name: 'session_test.set',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE, 'converters' => ['test_value' => '\\s+']],
  )]
  public function set(Request $request, $test_value): array {
    $request->getSession()->set('session_test_value', $test_value);

    return ['#markup' => $this->t('The current value of the stored session variable has been set to %val', ['%val' => $test_value])];
  }

  /**
   * Turns off session saving and then tries to save a value anyway.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $test_value
   *   A session value.
   */
  #[Route(
    path: '/session-test/no-set/{test_value}',
    name: 'session_test.no_set',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE, 'converters' => ['test_value' => '\\s+']],
  )]
  public function noSet(Request $request, $test_value): array {
    \Drupal::service('session_handler.write_safe')->setSessionWritable(FALSE);
    $this->set($request, $test_value);
    return ['#markup' => $this->t('session saving was disabled, and then %val was set', ['%val' => $test_value])];
  }

  /**
   * Sets a message to me displayed on the following page.
   */
  #[Route(
    path: '/session-test/set-message',
    name: 'session_test.set_message',
    requirements: ['_access' => 'TRUE'],
  )]
  public function setMessage(): Response {
    $this->messenger()->addStatus($this->t('This is a dummy message.'));
    return new Response((string) $this->t('A message was set.'));
    // Do not return anything, so the current request does not result in a
    // themed page with messages. The message will be displayed in the following
    // request instead.
  }

  /**
   * Sets a message but call drupal_save_session(FALSE).
   */
  #[Route(
    path: '/session-test/set-message-but-do-not-save',
    name: 'session_test.set_message_but_do_not_save',
    requirements: ['_access' => 'TRUE'],
  )]
  public function setMessageButDoNotSave(): array {
    \Drupal::service('session_handler.write_safe')->setSessionWritable(FALSE);
    $this->setMessage();
    return ['#markup' => ''];
  }

  /**
   * Only available if current user is logged in.
   */
  #[Route(
    path: '/session-test/is-logged-in',
    name: 'session_test.is_logged_in',
    requirements: ['_user_is_logged_in' => 'TRUE'],
  )]
  public function isLoggedIn(): array {
    return ['#markup' => $this->t('User is logged in.')];
  }

  /**
   * Returns the trace recorded by test proxy session handlers as JSON.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   */
  #[Route(
    path: '/session-test/trace-handler',
    name: 'session_test.trace_handler',
    requirements: ['_access' => 'TRUE'],
  )]
  public function traceHandler(Request $request): Response {
    // Increment trace-handler counter and save the session.
    $session = $request->getSession();
    $counter = $session->get('trace-handler', 0);
    $session->set('trace-handler', $counter + 1);
    $session->save();

    // Collect traces and return them in JSON format.
    $trace = \Drupal::service('session_test.session_handler_proxy_trace')->getArrayCopy();

    return new JsonResponse($trace);
  }

  /**
   * Returns an updated trace recorded by test proxy session handlers as JSON.
   *
   * The session data is rewritten without modification to invoke
   * `\SessionUpdateTimestampHandlerInterface::updateTimestamp`.
   *
   * Expects that there is an existing stacked session handler trace as recorded
   * by `traceHandler()`.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @throws \AssertionError
   */
  #[Route(
    path: '/session-test/trace-handler-rewrite-unmodified',
    name: 'session_test.trace_handler_rewrite_unmodified',
    requirements: ['_access' => 'TRUE'],
  )]
  public function traceHandlerRewriteUnmodified(Request $request): Response {
    // Assert that there is an existing session with stacked handler trace data.
    $session = $request->getSession();
    assert(
      is_int($session->get('trace-handler')) && $session->get('trace-handler') > 0,
      'Existing stacked session handler trace not found'
    );

    // Save unmodified session data.
    assert(
      ini_get('session.lazy_write'),
      'session.lazy_write must be enabled to invoke updateTimestamp()'
    );
    $session->save();

    // Collect traces and return them in JSON format.
    $trace = \Drupal::service('session_test.session_handler_proxy_trace')->getArrayCopy();

    return new JsonResponse($trace);
  }

  /**
   * Returns the values stored in the active session and the user ID.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  #[Route(
    path: '/session-test/get-session',
    name: 'session_test.get_session_basic_auth',
    requirements: ['_permission' => 'administer site configuration'],
    options: ['no_cache' => TRUE, '_auth' => ['basic_auth']],
  )]
  #[Route(
    path: '/session-test/get-session-no-auth',
    name: 'session_test.get_session_no_auth',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function getSession(Request $request): Response {
    return new JsonResponse(['session' => $request->getSession()->all(), 'user' => $this->currentUser()->id()]);
  }

  /**
   * Sets a test value on the session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $test_value
   *   A value to set on the session.
   */
  #[Route(
    path: '/session-test/set-session/{test_value}',
    name: 'session_test.set_session',
    requirements: ['_permission' => 'administer site configuration'],
    options: ['no_cache' => TRUE, '_auth' => ['basic_auth'], 'converters' => ['test_value' => '\\s+']],
  )]
  public function setSession(Request $request, $test_value): Response {
    $session = $request->getSession();
    $session->set('test_value', $test_value);
    return new JsonResponse(['session' => $session->all(), 'user' => $this->currentUser()->id()]);
  }

  /**
   * Sets the test flag in the session test bag.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  #[Route(
    path: '/session-test/set-bag-flag',
    name: 'session_test.set_bag_flag',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function setSessionBagFlag(Request $request): Response {
    /** @var \Drupal\session_test\Session\TestSessionBag */
    $bag = $request->getSession()->getBag(TestSessionBag::BAG_NAME);
    $bag->setFlag();
    return new Response();
  }

  /**
   * Clears the test flag from the session test bag.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  #[Route(
    path: '/session-test/clear-bag-flag',
    name: 'session_test.clear_bag_flag',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function clearSessionBagFlag(Request $request): Response {
    /** @var \Drupal\session_test\Session\TestSessionBag */
    $bag = $request->getSession()->getBag(TestSessionBag::BAG_NAME);
    $bag->clearFlag();
    return new Response();
  }

  /**
   * Prints a message if the flag in the session bag is set.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  #[Route(
    path: '/session-test/has-bag-flag',
    name: 'session_test.has_bag_flag',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function hasSessionBagFlag(Request $request): Response {
    /** @var \Drupal\session_test\Session\TestSessionBag */
    $bag = $request->getSession()->getBag(TestSessionBag::BAG_NAME);
    return new Response(empty($bag->hasFlag())
      ? (string) $this->t('Flag is absent from session bag')
      : (string) $this->t('Flag is present in session bag')
    );
  }

  /**
   * Trigger an exception when the session is written.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   */
  #[Route(
    path: '/session-test/trigger-write-exception',
    name: 'session_test.trigger_write_exception',
    requirements: ['_access' => 'TRUE'],
    options: ['no_cache' => TRUE],
  )]
  public function triggerWriteException(Request $request): Response {
    $session = $request->getSession();
    $session->set('test_value', 'Ensure session contains some data');

    // Move sessions table out of the way.
    $schema = \Drupal::database()->schema();
    $schema->renameTable('sessions', 'sessions_tmp');

    // There needs to be a session table, otherwise
    // InstallerRedirectTrait::shouldRedirectToInstaller() will instruct the
    // handleException::handleException to redirect to the installer.
    $schema->createTable('sessions', [
      'description' => "Fake sessions table missing some columns.",
      'fields' => [
        'sid' => [
          'description' => "A fake session ID column.",
          'type' => 'varchar_ascii',
          'length' => 128,
          'not null' => TRUE,
        ],
      ],
      'primary key' => ['sid'],
    ]);

    drupal_register_shutdown_function(function () {
      $schema = \Drupal::database()->schema();
      $schema->dropTable('sessions');
      $schema->renameTable('sessions_tmp', 'sessions');
    });

    return new Response();
  }

}
